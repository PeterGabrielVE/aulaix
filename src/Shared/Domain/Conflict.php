<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * The action conflicts with the current state of the resource (e.g. resending
 * an invitation that was already accepted). Rendered as 409.
 */
class Conflict extends DomainException {}
