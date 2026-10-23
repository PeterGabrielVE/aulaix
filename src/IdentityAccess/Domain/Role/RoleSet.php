<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Role;

/**
 * The roles a membership holds in its institution: an immutable set of
 * names, without duplicates.
 */
final readonly class RoleSet
{
    /**
     * @param  list<string>  $names
     */
    private function __construct(private array $names) {}

    public static function of(string ...$names): self
    {
        return new self(array_values(array_unique(array_filter(array_map('trim', $names), fn (string $name) => $name !== ''))));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    public function has(BaseRole|string $role): bool
    {
        return in_array($role instanceof BaseRole ? $role->value : $role, $this->names, true);
    }

    public function intersects(self $other): bool
    {
        return array_intersect($this->names, $other->names) !== [];
    }

    public function isEmpty(): bool
    {
        return $this->names === [];
    }

    /**
     * Base roles first, by priority; any other role after them.
     *
     * @return list<string>
     */
    public function inPriorityOrder(): array
    {
        $base = array_values(array_filter(
            array_map(fn (BaseRole $role) => $role->value, BaseRole::cases()),
            fn (string $name) => $this->has($name),
        ));

        return [...$base, ...array_values(array_diff($this->names, $base))];
    }

    /**
     * The highest-priority base role (IAM-R12.2), or null when there is none
     * (IAM-R12.3).
     */
    public function primaryRole(): ?BaseRole
    {
        foreach (BaseRole::cases() as $role) {
            if ($this->has($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The home screen of the primary role, or null when there is none.
     */
    public function homeScreen(): ?HomeScreen
    {
        return $this->primaryRole()?->homeScreen();
    }
}
