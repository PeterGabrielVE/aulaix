<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Guardian;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * What the institution knows of a guardian (EST-R03.1, R03.2, R03.9): names,
 * document and phone required; email and address optional.
 */
final readonly class GuardianData
{
    private const array MAX_LENGTHS = [
        'given_names' => 120,
        'family_names' => 120,
        'phone' => 30,
        'email' => 255,
        'address' => 500,
    ];

    private function __construct(
        public string $givenNames,
        public string $familyNames,
        public GuardianDocument $document,
        public string $phone,
        public ?string $email,
        public ?string $address,
    ) {}

    /**
     * Every broken rule is reported at once.
     *
     * @throws BusinessRuleViolations
     */
    public static function fromInput(
        ?string $givenNames,
        ?string $familyNames,
        ?string $documentType,
        ?string $document,
        ?string $phone,
        ?string $email = null,
        ?string $address = null,
    ): self {
        $values = [
            'given_names' => self::clean($givenNames),
            'family_names' => self::clean($familyNames),
            'phone' => self::clean($phone),
            'email' => self::clean($email) === null ? null : mb_strtolower(self::clean($email)),
            'address' => self::clean($address),
        ];
        $violations = [];

        foreach (['given_names' => 'Los nombres son obligatorios.', 'family_names' => 'Los apellidos son obligatorios.', 'phone' => 'El teléfono es obligatorio.'] as $field => $message) {
            if ($values[$field] === null) {
                $violations[] = BusinessRuleViolation::onField($field, $message);
            }
        }

        foreach (self::MAX_LENGTHS as $field => $max) {
            if ($values[$field] !== null && mb_strlen($values[$field]) > $max) {
                $violations[] = BusinessRuleViolation::onField($field, "Este dato admite como máximo {$max} caracteres.");
            }
        }

        if ($values['email'] !== null && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $violations[] = BusinessRuleViolation::onField('email', 'El correo no es válido.');
        }

        $guardianDocument = null;

        try {
            $guardianDocument = GuardianDocument::fromInput((string) $documentType, (string) $document);
        } catch (BusinessRuleViolation $invalid) {
            $violations[] = $invalid;
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self($values['given_names'], $values['family_names'], $guardianDocument, $values['phone'], $values['email'], $values['address']);
    }

    /**
     * Data read back from storage, already checked when it was saved.
     */
    public static function reconstitute(string $givenNames, string $familyNames, GuardianDocument $document, string $phone, ?string $email, ?string $address): self
    {
        return new self($givenNames, $familyNames, $document, $phone, $email, $address);
    }

    public function fullName(): string
    {
        return $this->givenNames.' '.$this->familyNames;
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
