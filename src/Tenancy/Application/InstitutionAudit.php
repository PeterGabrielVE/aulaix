<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Audit\Application\AuditSubject;
use AulaX\Tenancy\Domain\Institution;

/**
 * What the audit log keeps of an institution (INS-R06): its details as form
 * fields, never the content nor the path of its images.
 */
final class InstitutionAudit
{
    /**
     * @return array<string, string|int|list<string>|null>
     */
    public static function details(Institution $institution): array
    {
        return [
            'name' => $institution->name,
            'dea_code' => $institution->profile->deaCode,
            'rif' => $institution->profile->rif,
            'parish_id' => $institution->profile->parishId,
            'address' => $institution->profile->address,
            'phone' => $institution->profile->phone,
            'email' => $institution->profile->email,
            'type' => $institution->type?->value,
            'dependency' => $institution->dependency?->value,
            'levels' => $institution->levels?->codes(),
        ];
    }

    public static function subject(Institution $institution): AuditSubject
    {
        return AuditSubject::of('institution', $institution->id->value);
    }
}
