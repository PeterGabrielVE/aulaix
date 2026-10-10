<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * Base of every business exception. Each kind maps to one HTTP response in
 * bootstrap/app.php (constitution rule C-06), so use cases never deal with
 * HTTP and controllers never translate errors by hand.
 */
abstract class DomainException extends \DomainException
{
    /**
     * The form field the error belongs to, when it should be shown next to one.
     */
    public function field(): ?string
    {
        return null;
    }
}
