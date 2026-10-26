<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Activity;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\PlatformLog\ListPlatformEntriesQuery;
use AulaX\Audit\Application\PlatformLog\PlatformLogFilter;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use DateTimeImmutable;

/**
 * The platform log, for any operator (SPEC-014 PLT-R07.2), filtered by
 * date, operator and event type. Never an institution's log (PLT-R07.4):
 * Audit only reads platform_audit_entries here. Reading it is recorded too.
 */
final readonly class ListPlatformActivityQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private ListPlatformEntriesQuery $entries,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  ?string  $from  Y-m-d, from the start of that day (UTC).
     * @param  ?string  $to  Y-m-d, to the end of that day (UTC).
     * @return array{entries: list<array{id: int, occurredAt: string, eventType: string, subjectType: string, subjectId: string, origin: string, ip: ?string, operator: ?string, actorName: ?string, changes: list<array{field: string, from: mixed, to: mixed}>}>, total: int, page: int, perPage: int}
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(?string $from, ?string $to, ?string $operator, ?string $eventType, int $page): array
    {
        $current = PlatformAccess::operator($this->session, $this->operators);

        $result = ($this->entries)(new PlatformLogFilter(
            $from === null ? null : new DateTimeImmutable("{$from} 00:00:00 UTC"),
            $to === null ? null : new DateTimeImmutable("{$to} 23:59:59.999999 UTC"),
            $operator,
            $eventType,
        ), $page);

        $this->auditContext->actAsPlatformOperator($current->id->value);
        $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::activityViewed()));

        return [
            'entries' => array_map(fn (AuditEntryView $entry) => [
                'id' => $entry->id,
                'occurredAt' => $entry->occurredAt->format(DATE_ATOM),
                'eventType' => $entry->eventType,
                'subjectType' => $entry->subjectType,
                'subjectId' => $entry->subjectId,
                'origin' => $entry->origin,
                'ip' => $entry->ip,
                'operator' => $entry->operator,
                'actorName' => $entry->actorName,
                'changes' => array_map(fn (FieldChange $change) => ['field' => $change->field, 'from' => $change->from, 'to' => $change->to], $entry->changes),
            ], $result->entries),
            'total' => $result->total,
            'page' => $result->page,
            'perPage' => $result->perPage,
        ];
    }
}
