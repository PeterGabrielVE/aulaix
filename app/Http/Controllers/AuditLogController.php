<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesHandler;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesQuery;
use DateTimeImmutable;
use DateTimeZone;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The institution's audit log (SPEC-005). Access is limited by the
 * permission:ver-bitacora route middleware; Row Level Security limits every
 * query to the active institution.
 */
class AuditLogController extends Controller
{
    /**
     * Dates are shown, and filtered by whole days, in Venezuelan time (AUD-R05.5).
     */
    private const TIMEZONE = 'America/Caracas';

    public function index(AuditLogFilterRequest $request, ListAuditEntriesHandler $listEntries): Response
    {
        $filters = $request->validated();

        $page = $listEntries(new ListAuditEntriesQuery(
            from: self::localDay($filters['from'] ?? null, startOfDay: true),
            to: self::localDay($filters['to'] ?? null, startOfDay: false),
            actorId: isset($filters['actor']) ? (int) $filters['actor'] : null,
            eventType: $filters['event_type'] ?? null,
            subjectType: $filters['subject_type'] ?? null,
            subjectId: $filters['subject_id'] ?? null,
            page: (int) ($filters['page'] ?? 1),
        ));

        return Inertia::render('Audit/Index', [
            'entries' => [
                'data' => array_map(fn (AuditEntryView $entry) => self::summary($entry), $page->entries),
                'total' => $page->total,
                'page' => $page->page,
                'perPage' => $page->perPage,
                'lastPage' => max(1, (int) ceil($page->total / $page->perPage)),
            ],
            'filters' => array_diff_key($filters, ['page' => true]),
        ]);
    }

    public function show(int $entry, AuditLog $log): Response
    {
        $view = $log->find($entry);

        abort_if($view === null, 404);

        return Inertia::render('Audit/Show', [
            'entry' => [
                ...self::summary($view),
                'subjectType' => $view->subjectType,
                'subjectId' => $view->subjectId,
                'origin' => $view->origin,
                'ip' => $view->ip,
                'changes' => array_map(fn (FieldChange $change) => [
                    'field' => $change->field,
                    'from' => $change->from,
                    'to' => $change->to,
                ], $view->changes),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(AuditEntryView $entry): array
    {
        return [
            'id' => $entry->id,
            'occurredAt' => $entry->occurredAt->setTimezone(new DateTimeZone(self::TIMEZONE))->format('d/m/Y H:i'),
            'eventType' => $entry->eventType,
            'subject' => "{$entry->subjectType} #{$entry->subjectId}",
            'actor' => self::actorLabel($entry),
        ];
    }

    /**
     * An account that no longer exists keeps its entries, named as deleted
     * (AUD-R05.4). Changes without an account come from the console or the
     * system itself.
     */
    private static function actorLabel(AuditEntryView $entry): string
    {
        return match (true) {
            $entry->actorId !== null => $entry->actorName ?? 'Cuenta eliminada',
            $entry->origin === 'console' => "Consola ({$entry->operator})",
            default => 'Sistema',
        };
    }

    private static function localDay(?string $date, bool $startOfDay): ?DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }

        $time = $startOfDay ? '00:00:00' : '23:59:59';

        return (new DateTimeImmutable("{$date} {$time}", new DateTimeZone(self::TIMEZONE)))
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
