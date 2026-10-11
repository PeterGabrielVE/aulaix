<?php

use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Audit\Application\ListEntries\AuditLogFilter;
use AulaX\Audit\Application\ListEntries\AuditLogPage;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesHandler;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesQuery;

/**
 * A log that records how it was queried and answers with an empty page.
 */
function recordingLog(): AuditLog
{
    return new class implements AuditLog
    {
        /** @var list<array{0: AuditLogFilter, 1: int, 2: int}> */
        public array $queries = [];

        public function page(AuditLogFilter $filter, int $page, int $perPage): AuditLogPage
        {
            $this->queries[] = [$filter, $page, $perPage];

            return new AuditLogPage([], 0, $page, $perPage);
        }

        public function find(int $id): ?AuditEntryView
        {
            return null;
        }
    };
}

it('asks the log for the requested page, fifty entries at a time', function () {
    $log = recordingLog();

    (new ListAuditEntriesHandler($log))(new ListAuditEntriesQuery(page: 3));

    expect($log->queries[0][1])->toBe(3)
        ->and($log->queries[0][2])->toBe(50);
});

it('passes every filter through to the log', function () {
    $log = recordingLog();
    $from = new DateTimeImmutable('2026-09-01T00:00:00Z');
    $to = new DateTimeImmutable('2026-09-30T23:59:59Z');

    (new ListAuditEntriesHandler($log))(new ListAuditEntriesQuery(
        from: $from,
        to: $to,
        actorId: 7,
        eventType: 'evaluation.grade.changed',
        subjectType: 'grade',
        subjectId: '42',
    ));

    expect($log->queries[0][0])->toEqual(new AuditLogFilter($from, $to, 7, 'evaluation.grade.changed', 'grade', '42'));
});

it('starts from the first page when asked for an impossible one', function () {
    $log = recordingLog();

    (new ListAuditEntriesHandler($log))(new ListAuditEntriesQuery(page: 0));

    expect($log->queries[0][1])->toBe(1);
});
