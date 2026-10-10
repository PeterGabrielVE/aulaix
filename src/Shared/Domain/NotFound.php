<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * The requested resource does not exist, or is not visible to the actor.
 * Rendered as 404.
 */
class NotFound extends DomainException {}
