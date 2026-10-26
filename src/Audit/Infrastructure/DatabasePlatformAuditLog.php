<?php

declare(strict_types=1);

namespace AulaX\Audit\Infrastructure;

use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\AuditLogPage;
use AulaX\Audit\Application\PlatformLog\PlatformAuditLog;
use AulaX\Audit\Application\PlatformLog\PlatformLogFilter;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query service over platform_audit_entries only (A-08); its RLS lets rows
 * through only while no institution is active. The actor's name comes from
 * a left join, for the identity events of an account owner.
 */
final class DatabasePlatformAuditLog implements PlatformAuditLog
{
    public function page(PlatformLogFilter $filter, int $page, int $perPage): AuditLogPage
    {
        $query = DB::table('platform_audit_entries')
            ->leftJoin('users', 'users.id', '=', 'platform_audit_entries.actor_user_id')
            ->select('platform_audit_entries.*', 'users.name as actor_name')
            ->when($filter->from, fn (Builder $query, DateTimeInterface $from) => $query->where('platform_audit_entries.occurred_at', '>=', $from->format(DateTimeInterface::ATOM)))
            ->when($filter->to, fn (Builder $query, DateTimeInterface $to) => $query->where('platform_audit_entries.occurred_at', '<=', $to->format(DateTimeInterface::ATOM)))
            ->when($filter->operator, fn (Builder $query, string $operator) => $query->where('platform_audit_entries.operator', $operator))
            ->when($filter->eventTypePrefix, fn (Builder $query, string $prefix) => $query->where('platform_audit_entries.event_type', 'like', self::escapeLike($prefix).'%'));

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('platform_audit_entries.occurred_at')
            ->orderByDesc('platform_audit_entries.id')
            ->forPage($page, $perPage)
            ->get();

        return new AuditLogPage($rows->map(fn (object $row) => self::toView($row))->all(), $total, $page, $perPage);
    }

    private static function toView(object $row): AuditEntryView
    {
        $changes = [];

        foreach (json_decode($row->changes ?? '{}', true, flags: JSON_THROW_ON_ERROR) as $field => $values) {
            $changes[] = FieldChange::of($field, $values['from'], $values['to']);
        }

        return new AuditEntryView(
            id: (int) $row->id,
            occurredAt: (new DateTimeImmutable($row->occurred_at))->setTimezone(new DateTimeZone('UTC')),
            eventType: $row->event_type,
            subjectType: $row->subject_type,
            subjectId: $row->subject_id,
            actorId: $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            actorName: $row->actor_name,
            origin: $row->origin,
            ip: $row->ip,
            operator: $row->operator,
            changes: $changes,
        );
    }

    /**
     * Backslash first, so %, _ and \ match literally (S-09).
     */
    private static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}
