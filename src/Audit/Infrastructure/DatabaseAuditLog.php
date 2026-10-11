<?php

declare(strict_types=1);

namespace AulaX\Audit\Infrastructure;

use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Audit\Application\ListEntries\AuditLogFilter;
use AulaX\Audit\Application\ListEntries\AuditLogPage;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query service over the active institution's audit log (A-08). Row Level
 * Security limits it to that institution; the actor's name comes from a left
 * join, so an entry whose account is gone still shows, without a name.
 */
final class DatabaseAuditLog implements AuditLog
{
    public function page(AuditLogFilter $filter, int $page, int $perPage): AuditLogPage
    {
        $query = $this->filtered($filter);
        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('audit_entries.occurred_at')
            ->orderByDesc('audit_entries.id')
            ->forPage($page, $perPage)
            ->get();

        return new AuditLogPage(
            $rows->map(fn (object $row) => self::toView($row))->all(),
            $total,
            $page,
            $perPage,
        );
    }

    public function find(int $id): ?AuditEntryView
    {
        $row = $this->base()->where('audit_entries.id', $id)->first();

        return $row === null ? null : self::toView($row);
    }

    private function base(): Builder
    {
        return DB::table('audit_entries')
            ->leftJoin('users', 'users.id', '=', 'audit_entries.actor_user_id')
            ->select('audit_entries.*', 'users.name as actor_name');
    }

    private function filtered(AuditLogFilter $filter): Builder
    {
        return $this->base()
            ->when($filter->from, fn (Builder $query, DateTimeInterface $from) => $query->where('audit_entries.occurred_at', '>=', $from->format(DateTimeInterface::ATOM)))
            ->when($filter->to, fn (Builder $query, DateTimeInterface $to) => $query->where('audit_entries.occurred_at', '<=', $to->format(DateTimeInterface::ATOM)))
            ->when($filter->actorId, fn (Builder $query, int $actorId) => $query->where('audit_entries.actor_user_id', $actorId))
            ->when($filter->eventType, fn (Builder $query, string $type) => $query->where('audit_entries.event_type', $type))
            ->when($filter->subjectType, fn (Builder $query, string $type) => $query->where('audit_entries.subject_type', $type))
            ->when($filter->subjectId, fn (Builder $query, string $id) => $query->where('audit_entries.subject_id', $id));
    }

    private static function toView(object $row): AuditEntryView
    {
        $changes = [];

        foreach (json_decode($row->changes, true, flags: JSON_THROW_ON_ERROR) as $field => $values) {
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
}
