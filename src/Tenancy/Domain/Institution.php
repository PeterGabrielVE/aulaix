<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

/**
 * An institution (plantel) using AulaX, served at its own subdomain.
 *
 * It maintains its own name, details, type, dependency, levels and logo
 * (SPEC-006). Its subdomain and its status are the platform's: no method
 * here changes them (INS-R01.6). Immutable: every change returns a new
 * institution.
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
        public ?InstitutionDependency $dependency = null,
    ) {}

    /**
     * Type, levels and dependency are null until the institution says
     * them: there is no right value to assume for the institutions that
     * existed before.
     */
    public static function reconstitute(
        InstitutionId $id,
        string $name,
        Subdomain $subdomain,
        InstitutionStatus $status,
        InstitutionProfile $profile,
        ?InstitutionType $type = null,
        ?InstitutionLevels $levels = null,
        ?InstitutionDependency $dependency = null,
    ): self {
        return new self($id, self::validName($name), $subdomain, $status, $profile, $type, $levels, $dependency);
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
        return $this->with(name: self::validName($name));
    }

    /**
     * INS-R01.2. The logo is not part of the details: it stays.
     */
    public function changeProfile(InstitutionProfile $profile): self
    {
        return $this->with(profile: $profile->withLogo($this->profile->logoPath));
    }

    /**
     * INS-R02.5: an institution that stops being public loses its
     * dependency.
     */
    public function changeType(InstitutionType $type): self
    {
        return $this->with(type: $type, dependency: $type === InstitutionType::Publica ? $this->dependency : null);
    }

    /**
     * INS-R02.4, R02.5: only a public institution has a dependency.
     *
     * @throws BusinessRuleViolation
     */
    public function changeDependency(?InstitutionDependency $dependency): self
    {
        if ($dependency !== null && $this->type !== InstitutionType::Publica) {
            throw BusinessRuleViolation::onField('dependency', 'La dependencia solo aplica a planteles públicos.');
        }

        return $this->with(dependency: $dependency);
    }

    public function changeLevels(InstitutionLevels $levels): self
    {
        return $this->with(levels: $levels);
    }

    /**
     * INS-R04.1, R04.5: a new logo, or none.
     */
    public function changeLogo(?string $path): self
    {
        return $this->with(profile: $this->profile->withLogo($path));
    }

    /**
     * A copy with some fields changed; the dependency changes only when
     * given, so it can also be set back to null.
     */
    private function with(
        ?string $name = null,
        ?InstitutionProfile $profile = null,
        ?InstitutionType $type = null,
        ?InstitutionLevels $levels = null,
        InstitutionDependency|false|null $dependency = false,
    ): self {
        return new self(
            $this->id,
            $name ?? $this->name,
            $this->subdomain,
            $this->status,
            $profile ?? $this->profile,
            $type ?? $this->type,
            $levels ?? $this->levels,
            $dependency === false ? $this->dependency : $dependency,
        );
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
