<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * The official details of an institution, all optional (TEN-R01.4). DEA code
 * and RIF are unique across institutions: a rule between aggregates, checked
 * by the use case and backed by unique indexes. Neither has a required format
 * (INS-R01.8). The parish is referenced by id only, since Tenancy does not
 * import the Catalogs domain (A-06).
 */
final readonly class InstitutionProfile
{
    private const MAX_LENGTHS = [
        'dea_code' => 20,
        'rif' => 12,
        'address' => 500,
        'phone' => 30,
        'email' => 255,
    ];

    public function __construct(
        public ?string $deaCode = null,
        public ?string $rif = null,
        public ?int $parishId = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $logoPath = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * The details as a manager types them (INS-R01.4, R01.5, R01.8): blank
     * means empty, and every broken rule is reported at once. The logo is
     * not part of the form: it is kept from the current profile.
     *
     * @throws BusinessRuleViolations
     */
    public static function fromInput(
        ?string $deaCode = null,
        ?string $rif = null,
        ?int $parishId = null,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?self $keepingLogoOf = null,
    ): self {
        $values = [
            'dea_code' => self::clean($deaCode),
            'rif' => self::clean($rif),
            'address' => self::clean($address),
            'phone' => self::clean($phone),
            'email' => self::clean($email) === null ? null : mb_strtolower(self::clean($email)),
        ];

        $violations = [];

        foreach ($values as $field => $value) {
            if ($value !== null && mb_strlen($value) > self::MAX_LENGTHS[$field]) {
                $violations[] = BusinessRuleViolation::onField($field, 'Este dato admite como máximo '.self::MAX_LENGTHS[$field].' caracteres.');
            }
        }

        if ($values['email'] !== null && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $violations[] = BusinessRuleViolation::onField('email', 'El correo del plantel no es válido.');
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self(
            $values['dea_code'],
            $values['rif'],
            $parishId,
            $values['address'],
            $values['phone'],
            $values['email'],
            $keepingLogoOf?->logoPath,
        );
    }

    public function withLogo(?string $logoPath): self
    {
        return new self($this->deaCode, $this->rif, $this->parishId, $this->address, $this->phone, $this->email, $logoPath);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
