<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * The menu a person sees (NAV-R03.1, R03.2): the entries every role and
 * link of theirs allows, under their group's title, in order, leaving out
 * the groups with nothing to show. «Inicio» comes first, without a group.
 */
final class Navigation
{
    /**
     * @return list<array{group: ?MenuGroup, entries: non-empty-list<MenuEntry>}>
     */
    public static function for(Capabilities $capabilities): array
    {
        $groups = [];

        foreach (MenuEntry::cases() as $entry) {
            if (! $entry->isVisibleTo($capabilities)) {
                continue;
            }

            $key = $entry->group()->value ?? '';
            $groups[$key] ??= ['group' => $entry->group(), 'entries' => []];
            $groups[$key]['entries'][] = $entry;
        }

        return array_values($groups);
    }
}
