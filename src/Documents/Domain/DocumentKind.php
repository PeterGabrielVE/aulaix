<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * The seven institution documents of SPEC-012 (DOC-R01 – R04).
 */
enum DocumentKind: string
{
    case TermReport = 'term_report';
    case FinalReport = 'final_report';
    case StudyCertificate = 'study_certificate';
    case EnrollmentCertificate = 'enrollment_certificate';
    case WithdrawalCertificate = 'withdrawal_certificate';
    case GoodConductCertificate = 'good_conduct_certificate';
    case ProgressionCertificate = 'progression_certificate';

    public function isReport(): bool
    {
        return $this === self::TermReport || $this === self::FinalReport;
    }

    public function isCertificate(): bool
    {
        return ! $this->isReport();
    }

    /**
     * Time-sensitive certificates expire (90 days by default); historical
     * reports, withdrawals and progression/graduation records never expire.
     */
    public function hasExpiration(): bool
    {
        return match ($this) {
            self::StudyCertificate,
            self::EnrollmentCertificate,
            self::GoodConductCertificate => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TermReport => 'Boleta de lapso',
            self::FinalReport => 'Boleta final',
            self::StudyCertificate => 'Constancia de estudio',
            self::EnrollmentCertificate => 'Constancia de inscripción',
            self::WithdrawalCertificate => 'Constancia de retiro',
            self::GoodConductCertificate => 'Constancia de buena conducta',
            self::ProgressionCertificate => 'Constancia de prosecución / egreso',
        };
    }

    public function filePrefix(): string
    {
        return match ($this) {
            self::TermReport => 'boleta-lapso',
            self::FinalReport => 'boleta-final',
            self::StudyCertificate => 'constancia-estudio',
            self::EnrollmentCertificate => 'constancia-inscripcion',
            self::WithdrawalCertificate => 'constancia-retiro',
            self::GoodConductCertificate => 'constancia-buena-conducta',
            self::ProgressionCertificate => 'constancia-egreso',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind) => $kind->value, self::cases());
    }
}
