<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * The signature of an authority of the institution, as its documents will
 * show it (INS-R04.2, SPEC-012): who signs, in which position, and the image.
 * One per role and institution.
 */
final readonly class Signature
{
    private const MAX_LENGTH = 120;

    private function __construct(
        public SignatureRole $role,
        public string $signerName,
        public string $signerTitle,
        public string $filePath,
    ) {}

    /**
     * @throws BusinessRuleViolations
     */
    public static function of(SignatureRole $role, string $signerName, string $signerTitle, string $filePath): self
    {
        $signerName = trim($signerName);
        $signerTitle = trim($signerTitle);
        $violations = [];

        foreach (['signer_name' => $signerName, 'signer_title' => $signerTitle] as $field => $value) {
            if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
                $violations[] = BusinessRuleViolation::onField($field, 'Este dato es obligatorio y admite como máximo 120 caracteres.');
            }
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self($role, $signerName, $signerTitle, $filePath);
    }

    public static function reconstitute(SignatureRole $role, string $signerName, string $signerTitle, string $filePath): self
    {
        return new self($role, $signerName, $signerTitle, $filePath);
    }
}
