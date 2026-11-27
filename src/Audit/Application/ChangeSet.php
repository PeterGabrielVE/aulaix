<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

/**
 * Turns two snapshots of an object into the fields that actually changed
 * (AUD-R01.2), so an audit entry never stores the whole object.
 */
final class ChangeSet
{
    /**
     * Compared strictly: a value that changes type ('14' → 14) is a change.
     * A field present on one side only changed from or to null.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<FieldChange>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($after), ...array_keys($before)]) as $field) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;

            if ($from !== $to) {
                $changes[] = FieldChange::of($field, $from, $to);
            }
        }

        return $changes;
    }
}
