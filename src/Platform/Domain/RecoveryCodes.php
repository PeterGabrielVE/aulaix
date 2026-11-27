<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use InvalidArgumentException;

/**
 * The ten codes that stand in for the TOTP when its device is lost
 * (PLT-R01.5, R01.10). Kept as SHA-256 hashes, each used once (PLT-NF02).
 * Immutable: using one returns the new set.
 */
final readonly class RecoveryCodes
{
    public const int COUNT = 10;

    /**
     * @param  list<array{hash: string, used: bool}>  $codes
     */
    private function __construct(private array $codes) {}

    /**
     * @param  list<string>  $plain  Shown once to the operator, never kept.
     */
    public static function fromPlain(array $plain): self
    {
        if (count($plain) !== self::COUNT) {
            throw new InvalidArgumentException('An operator has exactly '.self::COUNT.' recovery codes.');
        }

        return new self(array_map(fn (string $code) => ['hash' => self::hash($code), 'used' => false], $plain));
    }

    /**
     * @param  list<array{hash: string, used: bool}>  $codes
     */
    public static function reconstitute(array $codes): self
    {
        return new self($codes);
    }

    public function accepts(string $code): bool
    {
        return $this->indexOf($code) !== null;
    }

    /**
     * @throws BusinessRuleViolation
     */
    public function use(string $code): self
    {
        $index = $this->indexOf($code) ?? throw BusinessRuleViolation::onField('code', 'Ese código de recuperación no es válido.');
        $codes = $this->codes;
        $codes[$index]['used'] = true;

        return new self($codes);
    }

    public function remaining(): int
    {
        return count(array_filter($this->codes, fn (array $code) => ! $code['used']));
    }

    /**
     * @return list<array{hash: string, used: bool}>
     */
    public function hashes(): array
    {
        return $this->codes;
    }

    private function indexOf(string $code): ?int
    {
        $hash = self::hash($code);

        foreach ($this->codes as $index => $stored) {
            if (! $stored['used'] && hash_equals($stored['hash'], $hash)) {
                return $index;
            }
        }

        return null;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', strtolower(str_replace(['-', ' '], '', trim($code))));
    }
}
