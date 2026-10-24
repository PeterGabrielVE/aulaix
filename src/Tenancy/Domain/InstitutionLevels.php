<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The education levels an institution offers: one at least, each once
 * (INS-R03.1, R03.2). Codes of the Catalogs level list; that they exist
 * there is checked by the use case, since Tenancy does not import the
 * Catalogs domain (A-06).
 */
final readonly class InstitutionLevels
{
    /**
     * @param  list<string>  $codes
     */
    private function __construct(private array $codes) {}

    /**
     * @param  list<string>  $codes
     *
     * @throws BusinessRuleViolation
     */
    public static function of(array $codes): self
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes), fn (string $code) => $code !== '')));

        if ($codes === []) {
            throw BusinessRuleViolation::onField('levels', 'Elige al menos un nivel.');
        }

        return new self($codes);
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->codes;
    }
}
