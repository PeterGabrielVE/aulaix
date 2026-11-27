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
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R06.1 – R06.5, R07.1: any operator of the session looks at
 * the doubtful cases and resolves them through IdentityAccess's use case,
 * previewing first; the operator is the one of the session.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->session = new FakeOperatorSession;
    $this->desk = new FakeConsolidationDesk;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;
    $this->desk->add('ana@example.com', 11, 12);

    $operator = Operator::invite('Luis', OperatorEmail::fromString('luis@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:x', 'SECRET', 1);
    $this->luis = $this->operators->save($operator);
    $this->session->start($this->operators->byId($this->luis), new DateTimeImmutable);
});

function consolidationUseCase(string $class): object
{
    $test = test();

    return match ($class) {
        ListCasesQuery::class => new ListCasesQuery($test->operators, $test->session, $test->desk),
        ShowCaseQuery::class => new ShowCaseQuery($test->operators, $test->session, $test->desk, $test->auditContext, $test->audit, $test->transactions),
        default => new $class($test->operators, $test->session, $test->desk, $test->auditContext),
    };
}

it('lists the cases to any operator (PLT-R06.1)', function () {
    $cases = consolidationUseCase(ListCasesQuery::class)();

    expect($cases)->toHaveCount(1)
        ->and(array_column($cases[0]['accounts'], 'id'))->toBe([11, 12]);
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
    $plan = consolidationUseCase(PreviewResolutionQuery::class)([11, 12], 'consolidate', 'Cédula verificada', 'TICKET-1');

    expect($plan)->toBe(['decision' => 'consolidate', 'survivorId' => 11, 'affectedIds' => [12], 'applied' => false])
        ->and($this->desk->resolutions[0])->toMatchArray(['operator' => 'luis@aulax.ve', 'dryRun' => true])
        ->and($this->auditContext->platformOperator)->toBe($this->luis->value);
});

it('resolves a case for real, with the operator of the session (PLT-R06.5)', function () {
    $resolution = consolidationUseCase(ResolveCaseHandler::class)([11, 12], 'separate', 'Son dos personas', null);

    expect($resolution['applied'])->toBeTrue()
        ->and($this->desk->resolutions[0])->toMatchArray(['operator' => 'luis@aulax.ve', 'decision' => 'separate', 'dryRun' => false])
        ->and($this->auditContext->platformOperator)->toBe($this->luis->value);
});

it('needs a decision (PLT-R06.2)', function () {
    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)([11, 12], 'merge', 'Motivo', null))
        ->toThrow(BusinessRuleViolation::class, 'Elige consolidar o mantener separadas.')
        ->and($this->desk->resolutions)->toBe([]);
});

it('passes on the refusals of IdentityAccess (PLT-R06.2, R06.4)', function () {
    expect(fn () => consolidationUseCase(ResolveCaseHandler::class)([11, 12], 'separate', ' ', null))->toThrow(BusinessRuleViolation::class, 'Falta el motivo.');
});

it('needs an operator session', function (string $class) {
    $this->session->end();

    expect(fn () => consolidationUseCase($class)(...match ($class) {
        ListCasesQuery::class => [],
        ShowCaseQuery::class => [11],
        default => [[11, 12], 'separate', 'Motivo', null],
    }))->toThrow(AuthorizationDenied::class)
        ->and($this->desk->resolutions)->toBe([]);
})->with([ListCasesQuery::class, ShowCaseQuery::class, PreviewResolutionQuery::class, ResolveCaseHandler::class]);
