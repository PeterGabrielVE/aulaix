<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * A business rule the request breaks. Rendered as a validation error (422 in
 * JSON, redirect back with the error in forms).
 */
class BusinessRuleViolation extends DomainException
{
    private ?string $field = null;

    public static function onField(string $field, string $message): static
    {
        $violation = new static($message);
        $violation->field = $field;

        return $violation;
    }

    public function field(): ?string
    {
        return $this->field;
    }
}
