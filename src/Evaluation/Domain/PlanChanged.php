<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\Conflict;

/**
 * Someone else saved the same plan meanwhile: the save is refused rather
 * than leaving a plan that does not add up to 100 %.
 */
final class PlanChanged extends Conflict
{
    public static function create(): self
    {
        return new self('Alguien guardó este plan mientras lo editabas. Vuelve a abrirlo y repite el cambio.');
    }
}
