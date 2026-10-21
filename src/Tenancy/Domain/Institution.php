<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

/**
 * An institution (plantel) using AulaX, served at its own subdomain.
 *
 * It maintains its own name, details, type, levels and logo (SPEC-006).
 * Its subdomain and its status are the platform's: no method here changes
 * them (INS-R01.6). Immutable: every change returns a new institution.
 */
final readonly class Institution
{
    private const MAX_NAME_LENGTH = 255;

    private function __construct(
        public InstitutionId $id,
        public string $name,
        public Subdomain $subdomain,
        public InstitutionStatus $status,
        public InstitutionProfile $profile,
        public ?InstitutionType $type = null,
        public ?InstitutionLevels $levels = null,
    ) {}

    /**
     * Type and levels are null until the institution says them: there is
     * no right value to assume for the institutions that existed before.
     */
    public static function reconstitute(
        InstitutionId $id,
        string $name,
        Subdomain $subdomain,
        InstitutionStatus $status,
        InstitutionProfile $profile,
        ?InstitutionType $type = null,
        ?InstitutionLevels $levels = null,
    ): self {
        return new self($id, self::validName($name), $subdomain, $status, $profile, $type, $levels);
    }

    public function isActive(): bool
    {
        return $this->status === InstitutionStatus::Active;
    }

    /**
     * INS-R01.7: shown at once in the central selector and in the institution.
     */
    public function rename(string $name): self
    {
        return new self($this->id, self::validName($name), $this->subdomain, $this->status, $this->profile, $this->type, $this->levels);
    }

    /**
     * INS-R01.2. The logo is not part of the details: it stays.
     */
    public function changeProfile(InstitutionProfile $profile): self
    {
        return new self($this->id, $this->name, $this->subdomain, $this->status, $profile->withLogo($this->profile->logoPath), $this->type, $this->levels);
    }

    public function changeType(InstitutionType $type): self
    {
        return new self($this->id, $this->name, $this->subdomain, $this->status, $this->profile, $type, $this->levels);
    }

    public function changeLevels(InstitutionLevels $levels): self
    {
        return new self($this->id, $this->name, $this->subdomain, $this->status, $this->profile, $this->type, $levels);
    }

    /**
     * INS-R04.1, R04.5: a new logo, or none.
     */
    public function changeLogo(?string $path): self
    {
        return new self($this->id, $this->name, $this->subdomain, $this->status, $this->profile->withLogo($path), $this->type, $this->levels);
    }

    private static function validName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw BusinessRuleViolation::onField('name', 'El nombre del plantel es obligatorio y admite como máximo 255 caracteres.');
        }

        return $name;
    }
}
