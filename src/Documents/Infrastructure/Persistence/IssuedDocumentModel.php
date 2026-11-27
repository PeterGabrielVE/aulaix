<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent record for `issued_documents` (under PostgreSQL RLS, S-01).
 *
 * @property int $id
 * @property int $institution_id
 * @property int $student_id
 * @property ?int $school_year_id
 * @property ?int $section_id
 * @property string $grade_code
 * @property string $kind
 * @property ?int $term
 * @property string $verification_code_hash
 * @property string $code_hint
 * @property string $pdf_path
 * @property string $pdf_sha256
 * @property int $pdf_bytes
 * @property string $student_full_name_snapshot
 * @property string $student_initials
 * @property string $student_id_last4
 * @property string $institution_name_snapshot
 * @property ?string $institution_dea_snapshot
 * @property string $period_label
 * @property int $issued_by
 * @property CarbonImmutable $issued_at
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $voided_at
 * @property ?int $voided_by
 * @property ?string $void_reason
 */
final class IssuedDocumentModel extends Model
{
    protected $table = 'issued_documents';

    protected $guarded = ['id', 'institution_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => 'integer',
            'pdf_bytes' => 'integer',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }
}
