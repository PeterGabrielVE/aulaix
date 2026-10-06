<?php

namespace App\Enums;

/**
 * An inactive user can't log in, and is logged out on their next request if
 * they already have a session (App\Http\Middleware\EnsureUserIsActive).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
