<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

use LogicException;

/**
 * One field of an audited change, with its value before and after. Values
 * are stored as JSON, so only scalars, null and lists of those are allowed.
 *
 * A field whose name looks like a credential is refused outright
 * (AUD-R01.4): recording it is a programming error, never a business rule,
 * so the change being audited fails and rolls back instead of leaking it.
 */
final readonly class FieldChange
{
    private const SENSITIVE = ['password', 'token', 'secret', 'hash', 'remember'];

    private function __construct(
        public string $field,
        public mixed $from,
        public mixed $to,
    ) {}

    public static function of(string $field, mixed $from, mixed $to): self
    {
        foreach (self::SENSITIVE as $fragment) {
            if (str_contains(strtolower($field), $fragment)) {
                throw new LogicException("The field '{$field}' looks like a credential and must never be audited.");
            }
        }

        if (! self::isStorable($from) || ! self::isStorable($to)) {
            throw new LogicException("The values of '{$field}' must be scalars, null or lists of those.");
        }

        return new self($field, $from, $to);
    }

    private static function isStorable(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        return is_array($value) && array_is_list($value)
            && array_filter($value, fn (mixed $item) => $item !== null && ! is_scalar($item)) === [];
    }
}
