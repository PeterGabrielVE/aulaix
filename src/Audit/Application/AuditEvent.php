<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

use LogicException;

/**
 * An auditable change described in business terms: what happened
 * ("evaluation.grade.changed"), to which object, and which fields changed.
 * Who, from where and when are added by the AuditTrail adapter.
 *
 * Event types are plain strings owned by the module that emits them, so this
 * module never depends on the modules it audits.
 */
final readonly class AuditEvent
{
    private const TYPE_FORMAT = '/^[a-z][a-z_]*(\.[a-z][a-z_]*){2,}$/';

    /**
     * @param  list<FieldChange>  $changes
     */
    private function __construct(
        public string $type,
        public AuditSubject $subject,
        public array $changes,
    ) {}

    /**
     * @param  list<FieldChange>  $changes  Empty for events that only record that something happened.
     */
    public static function of(string $type, AuditSubject $subject, array $changes = []): self
    {
        if (preg_match(self::TYPE_FORMAT, $type) !== 1) {
            throw new LogicException("Audit event types are shaped module.object.action, got '{$type}'.");
        }

        return new self($type, $subject, array_values($changes));
    }
}
