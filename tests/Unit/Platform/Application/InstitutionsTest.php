<?php

use AulaX\Platform\Application\Institutions\ListInstitutionsQuery;
use AulaX\Platform\Application\Institutions\SetInstitutionActiveHandler;
use AulaX\Platform\Application\Institutions\ShowInstitutionUsageQuery;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\FakePlatformInstitutions;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R04, R05, R07.1: operators look at institutions and their
 * usage; only a SuperAdmin switches one off or on, with a reason, and its
 * Administradores are told.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->session = new FakeOperatorSession;
    $this->institutions = new FakePlatformInstitutions;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;
    $this->institutions->add(7, 'Colegio Norte');
    $this->institutions->add(8, 'Liceo Sur', 'inactive');

    $this->boss = institutionsOperator($this->operators, 'jefa@aulax.ve', OperatorRole::SuperAdmin);
    $this->support = institutionsOperator($this->operators, 'soporte@aulax.ve', OperatorRole::Support);
    $this->session->start($this->operators->byId($this->boss), new DateTimeImmutable);
});

function institutionsOperator(InMemoryOperatorRepository $operators, string $email, OperatorRole $role): OperatorId
{
    $operator = Operator::invite('Operador', OperatorEmail::fromString($email), $role);
    $operator->activate('hash:x', "SECRET-{$email}", 1);

    return $operators->save($operator);
}

function setInstitutionActive(int $id, bool $active, string $reason): void
{
    $test = test();
    (new SetInstitutionActiveHandler($test->operators, $test->session, $test->institutions, $test->institutions, $test->auditContext, $test->audit, $test->transactions))($id, $active, $reason);
}

function showUsage(int $id): array
{
    $test = test();

    return (new ShowInstitutionUsageQuery($test->operators, $test->session, $test->institutions, $test->institutions, $test->auditContext, $test->audit, $test->transactions))($id);
}

it('lists the institutions to any operator', function () {
    $this->session->start($this->operators->byId($this->support), new DateTimeImmutable);

    $page = (new ListInstitutionsQuery($this->operators, $this->session, $this->institutions))(null, 'inactive', 1);

    expect(array_column($page['items'], 'name'))->toBe(['Liceo Sur']);
});

it('shows the usage of one institution, and records the query in the platform log', function () {
    $view = showUsage(7);

    expect($view['institution']['name'])->toBe('Colegio Norte')
        ->and($view['usage']['activeStudents'])->toBe(340)
        ->and($this->institutions->read)->toBe([7])
        ->and($this->audit->types())->toBe(['platform.institution.viewed'])
        ->and($this->auditContext->platformOperator)->toBe($this->boss->value);
});

it('says when the institution does not exist, without reading anything', function () {
    expect(fn () => showUsage(99))->toThrow(NotFound::class)
        ->and($this->institutions->read)->toBe([]);
});

it('deactivates an institution with a reason, records it and tells its Administradores', function () {
    setInstitutionActive(7, false, 'Falta de pago');

    expect($this->institutions->one(7)['status'])->toBe('inactive')
        ->and($this->audit->types())->toBe(['platform.institution.deactivated'])
        ->and($this->audit->changesOf(0))->toBe(['status' => ['from' => 'active', 'to' => 'inactive'], 'reason' => ['from' => null, 'to' => 'Falta de pago']])
        ->and($this->institutions->notices)->toBe([['institution' => 7, 'active' => false, 'reason' => 'Falta de pago']]);
});

it('reactivates an inactive institution, and tells its Administradores', function () {
    setInstitutionActive(8, true, 'Pago recibido');

    expect($this->institutions->one(8)['status'])->toBe('active')
        ->and($this->audit->types())->toBe(['platform.institution.reactivated'])
        ->and($this->institutions->notices[0]['active'])->toBeTrue();
});

it('needs a reason to deactivate', function () {
    expect(fn () => setInstitutionActive(7, false, '  '))->toThrow(BusinessRuleViolation::class, 'Indica el motivo.')
        ->and($this->institutions->one(7)['status'])->toBe('active');
});

it('does nothing, and tells nobody, when the status does not change', function () {
    setInstitutionActive(7, true, 'Sin cambios');

    expect($this->audit->types())->toBe([])
        ->and($this->institutions->notices)->toBe([]);
});

it('lets no Support switch an institution (PLT-R05.6)', function () {
    $this->session->start($this->operators->byId($this->support), new DateTimeImmutable);

    expect(fn () => setInstitutionActive(7, false, 'Falta de pago'))->toThrow(AuthorizationDenied::class)
        ->and($this->institutions->one(7)['status'])->toBe('active');
});

it('needs an operator session to look at anything', function () {
    $this->session->end();

    expect(fn () => showUsage(7))->toThrow(AuthorizationDenied::class)
        ->and(fn () => (new ListInstitutionsQuery($this->operators, $this->session, $this->institutions))(null, null, 1))->toThrow(AuthorizationDenied::class);
});
