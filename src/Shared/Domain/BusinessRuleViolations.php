<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

use InvalidArgumentException;

/**
 * Several broken business rules reported together (Notification pattern),
 * so a form shows every error at once instead of only the first. Rendered as
 * one validation error with all the fields (bootstrap/app.php).
 */
final class BusinessRuleViolations extends DomainException
{
    /**
     * @param  list<BusinessRuleViolation>  $violations
     */
    private function __construct(private readonly array $violations)
    {
        parent::__construct(implode(' ', array_map(fn (BusinessRuleViolation $violation) => $violation->getMessage(), $violations)));
    }

    public static function of(BusinessRuleViolation ...$violations): self
    {
        if ($violations === []) {
            throw new InvalidArgumentException('At least one violation is needed.');
        }

        return new self(array_values($violations));
    }

    /**
     * Throw the violations, if there are any.
     *
     * @param  list<BusinessRuleViolation>  $violations
     */
    public static function throwIfAny(array $violations): void
    {
        if ($violations !== []) {
            throw self::of(...$violations);
        }
    }

    /**
     * @return array<string, list<string>> Messages grouped by field ("general" when none).
     */
    public function messagesByField(): array
    {
        $messages = [];

        foreach ($this->violations as $violation) {
            $messages[$violation->field() ?? 'general'][] = $violation->getMessage();
        }

        return $messages;
    }
}
