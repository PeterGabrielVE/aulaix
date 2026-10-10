<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * The actor may not perform the action. Rendered as 403.
 */
class AuthorizationDenied extends DomainException {}
