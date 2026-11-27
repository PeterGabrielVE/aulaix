<?php

use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\Transfer\StudentTransferData;
use AulaX\Students\Application\Transfer\TransferredGuardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 ADR 0008 (EST-R07.5, R07.8, R11.3): the record of an approved
 * transfer is written in the destination, from the origin's transaction,
 * which then goes on as the origin.
 */
function transferData(): StudentTransferData
{
    return new StudentTransferData(
        PersonalData::fromInput('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'VE', null, null, 'Calle 5', null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('school_id', 'V11915432100'),
        HealthRecord::fromInput('Penicilina', null, null),
        [
            new TransferredGuardian(GuardianData::fromInput('María', 'Mora', 'national_id', 'V15432100', '0414'), Relationship::Mother, true, true),
            new TransferredGuardian(GuardianData::fromInput('Luis', 'Pérez', 'national_id', 'V20111222', '0424'), Relationship::Father, false, false),
        ],
        'Colegio Origen',
    );
}

function guardianIn(string $document, string $phone = '0212'): void
{
    DB::table('guardians')->insert(['given_names' => 'Luis A.', 'family_names' => 'Pérez', 'document_type' => 'national_id', 'document' => $document, 'phone' => $phone]);
}

it('creates the student in the destination, reusing its guardians, and goes back to the origin', function () {
    $destination = tenant();
    guardianIn('V20111222');
    $origin = tenant();

    DB::transaction(fn () => app(TransferCopier::class)->copyTo(InstitutionId::fromInt($destination->id), transferData()));

    expect(app(CurrentTenant::class)->institutionId()->value)->toBe($origin->id)
        ->and(DB::table('students')->count())->toBe(0);

    useTenant($destination);
    expect(DB::table('students')->first())->identification->toBe('V11915432100')->status->toBe('active')->allergies->toBe('Penicilina')
        ->and(DB::table('guardians')->orderBy('document')->get(['document', 'given_names', 'account_id'])->map(fn ($row) => (array) $row)->all())
        ->toBe([
            ['document' => 'V15432100', 'given_names' => 'María', 'account_id' => null],
            ['document' => 'V20111222', 'given_names' => 'Luis A.', 'account_id' => null],
        ])
        ->and(DB::table('guardianships')->count())->toBe(2)
        ->and(DB::table('audit_entries')->where('event_type', 'students.student.transferred_in')->count())->toBe(1);
});

it('writes nothing in the destination when the student is already there, and still goes back to the origin', function () {
    $destination = tenant();
    DB::table('students')->insert([
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100', 'status' => 'active',
    ]);
    $origin = tenant();

    expect(fn () => DB::transaction(fn () => app(TransferCopier::class)->copyTo(InstitutionId::fromInt($destination->id), transferData())))
        ->toThrow(Conflict::class);

    expect(app(CurrentTenant::class)->institutionId()->value)->toBe($origin->id);
    useTenant($destination);
    expect(DB::table('guardians')->count())->toBe(0)
        ->and(DB::table('students')->count())->toBe(1);
});
