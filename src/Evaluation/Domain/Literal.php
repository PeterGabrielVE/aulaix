<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The literal of Primaria, A to E (EVA-R04.2).
 */
enum Literal: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';

    /**
     * @throws MarkNotValid
     */
    public static function fromInput(?string $input): self
    {
        return self::tryFrom(strtoupper(trim((string) $input))) ?? throw MarkNotValid::literal();
    }
}
