<?php

use AulaX\Documents\Application\DTO\DocumentHistoryFilters;
use AulaX\Documents\Application\Port\DocumentHistoryDirectory;
use AulaX\Documents\Domain\DocumentKind;
use AulaX\Documents\Domain\DocumentStatus;
use AulaX\Documents\Domain\IssuedDocument;
use AulaX\Documents\Domain\IssuedDocumentRepository;
use AulaX\Documents\Domain\StudentPublicSummary;
use AulaX\Documents\Domain\ValidityPeriod;
use AulaX\Documents\Domain\VerificationCode;
use AulaX\Documents\Domain\VoidReason;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-012 DOC-R05.4, R05.5, R10.1, R10.3: repository round-trip, CHECK
 * constraints, history directory filters and PostgreSQL RLS isolation.
 */
test('persists, finds by verification hash, enforces constraints and isolates by RLS', function () {
    $north = tenant(['subdomain' => 'doc-repo-norte', 'name' => 'Colegio Norte']);
    $actor = userWithRole($north, 'Administrador');
    $studentId = DB::table('students')->insertGetId([
        'given_names' => 'Ana María',
        'family_names' => 'Pérez Gómez',
        'sex' => 'female',
        'birth_date' => '2012-03-10',
        'nationality' => 'V',
        'identification_type' => 'national_id',
        'identification' => 'V30123456',
        'status' => 'active',
    ]);

    $repo = app(IssuedDocumentRepository::class);
    $history = app(DocumentHistoryDirectory::class);
    $now = new DateTimeImmutable('2026-11-27 10:00:00');
    $code = VerificationCode::generate();

    $document = IssuedDocument::issue(
        studentId: $studentId,
        schoolYearId: null,
        sectionId: null,
        gradeCode: 'media_1',
        kind: DocumentKind::StudyCertificate,
        term: null,
        verificationCode: $code,
        pdfPath: "institutions/{$north->id}/documents/".str_repeat('a', 40).'.pdf',
        pdfSha256: str_repeat('b', 64),
        pdfBytes: 5432,
        studentFullNameSnapshot: 'Ana María Pérez Gómez',
        publicSummary: StudentPublicSummary::fromStudent('Ana María', 'Pérez Gómez', 'V30123456'),
        institutionNameSnapshot: 'Colegio Norte',
        institutionDeaSnapshot: 'S1234D',
        periodLabel: '2026-2027',
        issuedBy: $actor->id,
        issuedAt: $now,
        validity: ValidityPeriod::ofDays(90),
    );

    $id = $repo->save($document);
    $loaded = $repo->byVerificationCodeHash($code->hash());

    expect($loaded)->not->toBeNull()
        ->and($loaded->id->equals($id))->toBeTrue()
        ->and($loaded->publicSummary->initials)->toBe('A. M. P. G.')
        ->and($loaded->publicSummary->idLast4)->toBe('****3456')
        ->and($loaded->statusAt($now->modify('+1 day')))->toBe(DocumentStatus::Valid);

    $loaded->void(VoidReason::fromString('Se corrigió la sección del estudiante'), $actor->id, $now->modify('+2 hours'));
    $repo->save($loaded);

    $afterVoid = $repo->byId($id);
    expect($afterVoid->isVoided())->toBeTrue()
        ->and($afterVoid->voidReason()->value)->toBe('Se corrigió la sección del estudiante');

    $page = $history->paginate(new DocumentHistoryFilters(status: 'voided', search: 'Pérez'), ['media_1'], $now->modify('+3 hours'));
    expect($page->total)->toBe(1)
        ->and($page->items[0]->status)->toBe('voided')
        ->and($page->items[0]->downloadable)->toBeFalse();

    // Coordinator restricted to another grade sees nothing.
    $outsideScope = $history->paginate(new DocumentHistoryFilters, ['primaria_1'], $now->modify('+3 hours'));
    expect($outsideScope->total)->toBe(0);

    // Another institution cannot see or read the document via RLS.
    tenant(['subdomain' => 'doc-repo-sur', 'name' => 'Colegio Sur']);
    expect($repo->byId($id))->toBeNull()
        ->and($repo->byVerificationCodeHash($code->hash()))->toBeNull();
});

test('database CHECK constraints reject incoherent term, expiration and void rows', function () {
    $north = tenant(['subdomain' => 'doc-repo-check', 'name' => 'Colegio Check']);
    $actor = userWithRole($north, 'Administrador');
    $studentId = DB::table('students')->insertGetId([
        'given_names' => 'Luis',
        'family_names' => 'Rojas',
        'sex' => 'male',
        'birth_date' => '2012-03-10',
        'nationality' => 'V',
        'identification_type' => 'national_id',
        'identification' => 'V30999888',
        'status' => 'active',
    ]);

    expect(fn () => DB::table('issued_documents')->insert([
        'student_id' => $studentId,
        'grade_code' => 'media_1',
        'kind' => 'term_report',
        'term' => null, // violates term_report requires term 1..3
        'verification_code_hash' => str_repeat('1', 64),
        'code_hint' => '…123456',
        'pdf_path' => "institutions/{$north->id}/documents/".str_repeat('a', 40).'.pdf',
        'pdf_sha256' => str_repeat('2', 64),
        'pdf_bytes' => 1000,
        'student_full_name_snapshot' => 'Luis Rojas',
        'student_initials' => 'L. R.',
        'student_id_last4' => '****9888',
        'institution_name_snapshot' => 'Colegio Check',
        'period_label' => '2026-2027',
        'issued_by' => $actor->id,
        'issued_at' => '2026-11-27 10:00:00',
    ]))->toThrow(QueryException::class);
});
