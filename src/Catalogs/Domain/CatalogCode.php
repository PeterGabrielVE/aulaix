<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain;

/**
 * The stable identifier of a catalog entry (VE-A, VE-A-01, MAT). Entries are
 * matched by code when synchronizing, never by id or name.
 */
final readonly class CatalogCode
{
    private const MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH || preg_match('/\s/', $value) === 1) {
            throw InvalidCatalog::invalidCode($value);
        }

        return new self($value);
    }

    /**
     * Child codes extend their parent's with a dash: VE-A-01 belongs to VE-A.
     */
    public function isChildOf(self $parent): bool
    {
        return str_starts_with($this->value, $parent->value.'-');
    }
}
