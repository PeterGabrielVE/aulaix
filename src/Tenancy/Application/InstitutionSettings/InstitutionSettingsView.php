<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionSettings;

final readonly class InstitutionSettingsView
{
    /**
     * @param  ?string  $type  publica, privada or subvencionada; null until the institution says.
     * @param  list<string>  $levels  Codes of the catalog's education levels; empty until chosen.
     */
    public function __construct(
        public ?string $type,
        public array $levels,
    ) {}
}
