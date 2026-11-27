<?php

use AulaX\Platform\Application\Consolidation\ListCasesQuery;
use AulaX\Platform\Application\Consolidation\PreviewResolutionQuery;
use AulaX\Platform\Application\Consolidation\ResolveCaseHandler;
use AulaX\Platform\Application\Consolidation\ShowCaseQuery;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeConsolidationDesk;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\InMemoryConsolidationPreviews;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R06.1 – R06.5, R07.1; SPEC-017 RSP-R05, R07: any operator of
 * the session looks at the doubtful cases (audited without PII) and resolves
 * them through IdentityAccess's use case only after a matching server-side
 * preview from the last 10 minutes.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->session = new FakeOperatorSession;
    $this->desk = new FakeConsolidationDesk;
    $this->previews = new InMemoryConsolidationPreviews;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;
    $this->desk->add('ana@example.com', 11, 12);

    $operator = Operator::invite('Luis', OperatorEmail::fromString('luis@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:x', 'SECRET', 1);
    $this->luis = $this->operators->save($operator);
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-26 10:00:00'));
    $this->session->start($this->operators->byId($this->luis), $this->clock->now);
});

function consolidationUseCase(string $class): object
{
    $test = test();

    return match ($class) {
        ListCasesQuery::class => new ListCasesQuery($test->operators, $test->session, $test->desk, $test->auditContext, $test->audit, $test->transactions, $test->clock),
        ShowCaseQuery::class => new ShowCaseQuery($test->operators, $test->session, $test->desk, $test->auditContext, $test->audit, $test->transactions),
        PreviewResolutionQuery::class => new PreviewResolutionQuery($test->operators, $test->session, $test->desk, $test->auditContext, $test->clock, $test->previews),
        ResolveCaseHandler::class => new ResolveCaseHandler($test->operators, $test->session, $test->desk, $test->auditContext, $test->clock, $test->previews, $test->audit, $test->transactions),
    };
}

it('lists the cases to any operator (PLT-R06.1)', function () {
    $cases = consolidationUseCase(ListCasesQuery::class)();
    $changes = $this->audit->changesOf(0);

    expect($cases)->toHaveCount(1)
        ->and(array_column($cases[0]['accounts'], 'id'))->toBe([11, 12])
        ->and($this->audit->types())->toBe(['platform.consolidation_cases.listed'])
        ->and($changes)->toBe(['accounts' => ['from' => null, 'to' => [11, 12]]])
        ->and(json_encode($changes))->not->toContain('ana@example.com')
        ->and(json_encode($changes))->not->toContain('Cuenta 11');
});

it('shows one case by any of its accounts, and records the query (PLT-R07.1)', function () {
    $case = consolidationUseCase(ShowCaseQuery::class)(12);

    expect($case['email'])->toBe('ana@example.com')
        ->and($this->audit->types())->toBe(['platform.consolidation_case.viewed'])
        ->and($this->auditContext->platformOperator)->toBe($this->luis->value);
});

it('says when no case holds that account', function () {
    expect(fn () => consolidationUseCase(ShowCaseQuery::class)(99))->toThrow(NotFound::class)
        ->and($this->audit->types())->toBe([]);
});

it('previews a resolution as a dry run, with the operator of the session (PLT-R06.3)', function () {
    $plan = consolidationUseCase(PreviewResolutionQuery::class)(11, [11, 12], 'consolidate', 'Cédula verificada', 'TICKET-1');

    expect($plan)->toBe(['decision' => 'consolidate', 'survivorId' => 11, 'affectedIds' => [12], 'applied' => false])
        ->and($this->desk->resolutions[0])->toMatchArray(['operator' => 'luis@aulax.ve', 'dryRun' => true])
        ->and($this->auditContext->platformOperator)->toBe($this->luis->value)
        ->and($this->previews->matches([11, 12], 'consolidate', $this->clock->now, 600))->toBeTrue();
});

it('resolves a case for real, with the operator of the session (PLT-R06.5)', function () {
    consolidationUseCase(PreviewResolutionQuery::class)(11, [11, 12], 'separate', 'Son dos personas', null);

    $resolution = consolidationUseCase(ResolveCaseHandler::class)(11, [11, 12], 'separate', 'Son dos personas', null);

    expect($resolution['applied'])->toBeTrue()
        ->and($this->desk->resolutions[1])->toMatchArray(['operator' => 'luis@aulax.ve', 'decision' => 'separate', 'dryRun' => false])
        ->and($this->auditContext->platformOperator)->toBe($this->luis->value)
        ->and($this->previews->stored)->toBeNull();
});

it('refuses resolving without a matching preview, when expired, or with a different decision or accounts, and audits the refusal (RSP-R05.1, R05.2)', function (Closure $setup) {
    $setup->call($this);

    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)(11, [11, 12], 'separate', 'Motivo', null))
        ->toThrow(BusinessRuleViolation::class, 'Revisa la vista previa antes de resolver.')
        ->and($this->audit->types())->toContain('platform.consolidation.resolve_refused');
})->with([
    'no preview' => [fn () => null],
    'expired preview' => [function () {
        consolidationUseCase(PreviewResolutionQuery::class)(11, [11, 12], 'separate', 'Motivo', null);
        $this->clock->now = $this->clock->now->modify('+11 minutes');
        $this->session->confirmTotp($this->clock->now);
    }],
    'different decision' => [fn () => consolidationUseCase(PreviewResolutionQuery::class)(11, [11, 12], 'consolidate', 'Motivo', 'REF-1')],
    'different accounts' => [fn () => consolidationUseCase(PreviewResolutionQuery::class)(11, [11, 13], 'separate', 'Motivo', null)],
]);

it('refuses when the route case account is not part of the submitted accounts (RSP-R05.4)', function () {
    expect(fn () => consolidationUseCase(PreviewResolutionQuery::class)(99, [11, 12], 'separate', 'Motivo', null))
        ->toThrow(BusinessRuleViolation::class, 'Esas cuentas no son las de este caso.')
        ->and(fn () => consolidationUseCase(ResolveCaseHandler::class)(99, [11, 12], 'separate', 'Motivo', null))
        ->toThrow(BusinessRuleViolation::class, 'Esas cuentas no son las de este caso.');
});

it('keeps the preview valid when resolution fails inside IdentityAccess', function () {
    $this->previews->remember([11, 12], 'separate', $this->clock->now);

    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)(11, [11, 12], 'separate', ' ', null))
        ->toThrow(BusinessRuleViolation::class, 'Falta el motivo.')
        ->and($this->previews->matches([11, 12], 'separate', $this->clock->now, 600))->toBeTrue();
});

it('needs a decision (PLT-R06.2)', function () {
    $this->previews->remember([11, 12], 'merge', $this->clock->now);

    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)(11, [11, 12], 'merge', 'Motivo', null))
        ->toThrow(BusinessRuleViolation::class, 'Elige consolidar o mantener separadas.')
        ->and($this->desk->resolutions)->toBe([]);
});

it('passes on the refusals of IdentityAccess (PLT-R06.2, R06.4)', function () {
    $this->previews->remember([11, 12], 'separate', $this->clock->now);

    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)(11, [11, 12], 'separate', ' ', null))->toThrow(BusinessRuleViolation::class, 'Falta el motivo.');
});

it('needs an operator session', function (string $class) {
    $this->session->end();

    expect(fn () => consolidationUseCase($class)(...match ($class) {
        ListCasesQuery::class => [],
        ShowCaseQuery::class => [11],
        default => [11, [11, 12], 'separate', 'Motivo', null],
    }))->toThrow(AuthorizationDenied::class)
        ->and($this->desk->resolutions)->toBe([]);
})->with([ListCasesQuery::class, ShowCaseQuery::class, PreviewResolutionQuery::class, ResolveCaseHandler::class]);
