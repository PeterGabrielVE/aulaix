<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\ShowLayout;

/**
 * What every page of the institution shows around its content.
 */
final readonly class LayoutView
{
    /**
     * @param  list<array{group: ?string, entries: list<string>}>  $navigation  Keys of MenuGroup and MenuEntry, in order.
     */
    public function __construct(
        public array $navigation,
        public ?string $yearName,
        public ?int $openTerm,
        public ?string $primaryRole,
        public bool $canSwitchInstitution,
    ) {}
}
