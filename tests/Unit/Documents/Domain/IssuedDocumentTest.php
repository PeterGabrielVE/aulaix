<?php

use AulaX\Documents\Domain\DocumentKind;
use AulaX\Documents\Domain\DocumentStatus;
use AulaX\Documents\Domain\GoodConductNote;
use AulaX\Documents\Domain\IssuedDocument;
use AulaX\Documents\Domain\StudentPublicSummary;
use AulaX\Documents\Domain\ValidityPeriod;
use AulaX\Documents\Domain\VerificationCode;
use AulaX\Documents\Domain\VoidReason;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-012 DOC-R04.6, R05.4, R06.1 – R06.3: IssuedDocument invariants,
 * validity windows, optional good-conduct note and irreversible voiding.
 */
it('tracks validity, expiration, immutability and irreversible voiding', function () {
    $issuedAt = new DateTimeImmutable('2026-11-27 10:00:00');
    $code = VerificationCode::generate();
    $summary = StudentPublicSummary::fromStudent('Ana', 'Pérez', 'V30123456');

    $certificate = IssuedDocument::issue(
        studentId: 7,
        schoolYearId: 1,
        sectionId: 3,
        gradeCode: 'media_1',
        kind: DocumentKind::StudyCertificate,
        term: null,
        verificationCode: $code,
        pdfPath: 'institutions/1/documents/'.str_repeat('a', 40).'.pdf',
        pdfSha256: str_repeat('b', 64),
        pdfBytes: 12345,
        studentFullNameSnapshot: 'Ana Pérez',
        publicSummary: $summary,
        institutionNameSnapshot: 'Colegio Norte',
        institutionDeaSnapshot: 'S1234D',
        periodLabel: '2026-2027',
        issuedBy: 9,
        issuedAt: $issuedAt,
        validity: ValidityPeriod::ofDays(30),
    );

    expect($certificate->expiresAt)->toEqual($issuedAt->modify('+30 days'))
        ->and($certificate->statusAt($issuedAt->modify('+15 days')))->toBe(DocumentStatus::Valid)
        ->and($certificate->statusAt($issuedAt->modify('+31 days')))->toBe(DocumentStatus::Expired)
        ->and($certificate->isDownloadable())->toBeTrue();

    $certificate->void(VoidReason::fromString('Error en la sección registrada'), 9, $issuedAt->modify('+1 hour'));

    expect($certificate->isVoided())->toBeTrue()
        ->and($certificate->isDownloadable())->toBeFalse()
        ->and($certificate->statusAt($issuedAt->modify('+2 hours')))->toBe(DocumentStatus::Voided)
        ->and($certificate->statusAt($issuedAt->modify('+40 days')))->toBe(DocumentStatus::Voided)
        ->and(fn () => $certificate->void(VoidReason::fromString('Segundo intento'), 9, $issuedAt->modify('+2 hours')))
        ->toThrow(BusinessRuleViolation::class, 'Ese documento ya fue anulado.');

    $report = IssuedDocument::issue(
        studentId: 7,
        schoolYearId: 1,
        sectionId: 3,
        gradeCode: 'media_1',
        kind: DocumentKind::TermReport,
        term: 1,
        verificationCode: VerificationCode::generate(),
        pdfPath: 'institutions/1/documents/'.str_repeat('c', 40).'.pdf',
        pdfSha256: str_repeat('d', 64),
        pdfBytes: 9876,
        studentFullNameSnapshot: 'Ana Pérez',
        publicSummary: $summary,
        institutionNameSnapshot: 'Colegio Norte',
        institutionDeaSnapshot: null,
        periodLabel: '2026-2027 · 1.er lapso',
        issuedBy: 9,
        issuedAt: $issuedAt,
    );

    expect($report->expiresAt)->toBeNull()
        ->and($report->statusAt($issuedAt->modify('+5 years')))->toBe(DocumentStatus::Valid);
});

it('validates validity days, good conduct note length and void reason bounds', function () {
    expect(ValidityPeriod::ofDays(null)->days)->toBe(90)
        ->and(ValidityPeriod::ofDays(1)->days)->toBe(1)
        ->and(ValidityPeriod::ofDays(365)->days)->toBe(365)
        ->and(fn () => ValidityPeriod::ofDays(0))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => ValidityPeriod::ofDays(366))->toThrow(BusinessRuleViolation::class);

    expect(GoodConductNote::fromNullable(null))->toBeNull()
        ->and(GoodConductNote::fromNullable('   '))->toBeNull()
        ->and(GoodConductNote::fromNullable('  Destacada participación  ')->value)->toBe('Destacada participación')
        ->and(fn () => GoodConductNote::fromNullable(str_repeat('a', 301)))->toThrow(BusinessRuleViolation::class);

    expect(fn () => VoidReason::fromString('mal'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => VoidReason::fromString(str_repeat('x', 501)))->toThrow(BusinessRuleViolation::class);
});
