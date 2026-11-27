<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * The titled groups of the menu, in order (NAV-R03.2).
 */
enum MenuGroup: string
{
    case Academic = 'academic';
    case Students = 'students';
    case Institution = 'institution';
}
