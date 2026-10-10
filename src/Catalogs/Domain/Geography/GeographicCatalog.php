<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Geography;

use AulaX\Catalogs\Domain\CatalogCode;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * The reference geographic catalog (states → municipalities → parishes),
 * validated as a whole. It is additive: synchronizing it inserts and renames
 * by code but never removes official entries (CAT-R03.1, CAT-R03.2).
 */
final readonly class GeographicCatalog
{
    /**
     * @param  list<StateEntry>  $states
     */
    private function __construct(private array $states) {}

    public static function of(StateEntry ...$states): self
    {
        $stateCodes = $municipalityCodes = $parishCodes = [];

        foreach ($states as $state) {
            if (LegacyPlaceholder::isPlaceholderState($state->code->value)) {
                throw InvalidCatalog::placeholderCode($state->code->value);
            }

            self::claim($stateCodes, $state->code);
            self::assertHasChildren($state->code, $state->municipalities);

            foreach ($state->municipalities as $municipality) {
                self::claim($municipalityCodes, $municipality->code);
                self::assertChildOf($municipality->code, $state->code);
                self::assertHasChildren($municipality->code, $municipality->parishes);

                foreach ($municipality->parishes as $parish) {
                    self::claim($parishCodes, $parish->code);
                    self::assertChildOf($parish->code, $municipality->code);
                }
            }
        }

        return new self(array_values($states));
    }

    /**
     * @return list<StateEntry>
     */
    public function states(): array
    {
        return $this->states;
    }

    /**
     * @param  array<string, true>  $seen
     */
    private static function claim(array &$seen, CatalogCode $code): void
    {
        if (isset($seen[$code->value])) {
            throw InvalidCatalog::duplicateCode($code->value);
        }

        $seen[$code->value] = true;
    }

    /**
     * @param  list<mixed>  $children
     */
    private static function assertHasChildren(CatalogCode $code, array $children): void
    {
        if ($children === []) {
            throw InvalidCatalog::emptyBranch($code->value);
        }
    }

    private static function assertChildOf(CatalogCode $child, CatalogCode $parent): void
    {
        if (! $child->isChildOf($parent)) {
            throw InvalidCatalog::misplacedChild($child->value, $parent->value);
        }
    }
}
