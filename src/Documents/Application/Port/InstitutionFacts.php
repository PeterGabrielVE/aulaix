<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

use AulaX\Documents\Application\DTO\InstitutionHeaderData;

/**
 * Reads the open institution's profile, logo, signatures and missing notices
 * from Tenancy (A-06, DOC-R05.1 – R05.3).
 */
interface InstitutionFacts
{
    public function header(): InstitutionHeaderData;

    /**
     * @return list<string>
     */
    public function missingNotices(): array;

    /**
     * Public verification URL on the open institution's subdomain for a raw
     * verification code (DOC-R07.1, TEN-R03.6).
     */
    public function verificationUrlFor(string $code): string;
}
