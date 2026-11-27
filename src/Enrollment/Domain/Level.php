<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The level of a grade, by its catalog code (SPEC-003): the rules of
 * promotion and age depend on it.
 */
enum Level: string
{
    case Initial = 'inicial';
    case Primary = 'primaria';
    case Secondary = 'media_general';
}
