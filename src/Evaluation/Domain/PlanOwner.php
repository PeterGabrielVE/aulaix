<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use InvalidArgumentException;

/**
 * Whose plan of activities it is (ADR 0013): a section, or a group of
 * pending subjects (SPEC-016). Exactly one of the two.
 */
final readonly class PlanOwner
{
    private function __construct(
        public ?int $sectionId,
        public ?int $pendingGroupId,
    ) {}

    public static function section(int $sectionId): self
    {
        if ($sectionId <= 0) {
            throw new InvalidArgumentException('A plan belongs to an existing section.');
        }

        return new self($sectionId, null);
    }

    public static function pendingGroup(int $pendingGroupId): self
    {
        if ($pendingGroupId <= 0) {
            throw new InvalidArgumentException('A plan belongs to an existing group of pending subjects.');
        }

        return new self(null, $pendingGroupId);
    }

    public function isSection(): bool
    {
        return $this->sectionId !== null;
    }

    public function equals(self $other): bool
    {
        return $this->sectionId === $other->sectionId && $this->pendingGroupId === $other->pendingGroupId;
    }
}
