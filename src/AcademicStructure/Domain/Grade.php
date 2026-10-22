<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The official grades of the MPPE (EAC-R01.1), in order. Inicial has the
 * Maternal rooms (I to III) and the Preescolar levels (1st to 3rd).
 */
enum Grade: string
{
    case Maternal1 = 'maternal_1';
    case Maternal2 = 'maternal_2';
    case Maternal3 = 'maternal_3';
    case Preescolar1 = 'preescolar_1';
    case Preescolar2 = 'preescolar_2';
    case Preescolar3 = 'preescolar_3';
    case Primaria1 = 'primaria_1';
    case Primaria2 = 'primaria_2';
    case Primaria3 = 'primaria_3';
    case Primaria4 = 'primaria_4';
    case Primaria5 = 'primaria_5';
    case Primaria6 = 'primaria_6';
    case Media1 = 'media_1';
    case Media2 = 'media_2';
    case Media3 = 'media_3';
    case Media4 = 'media_4';
    case Media5 = 'media_5';

    private const array ORDINALS = [1 => '1.er', 2 => '2.º', 3 => '3.er', 4 => '4.º', 5 => '5.º', 6 => '6.º'];

    private const array ROMANS = [1 => 'I', 2 => 'II', 3 => 'III'];

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromInput(?string $value, string $field = 'grade'): self
    {
        return self::tryFrom((string) $value) ?? throw BusinessRuleViolation::onField($field, 'Elige un grado de la lista.');
    }

    /**
     * The grades of these education levels, in order (EAC-R01.2).
     *
     * @param  list<string>  $levels  Codes of the catalog's levels.
     * @return list<self>
     */
    public static function ofLevels(array $levels): array
    {
        return array_values(array_filter(self::cases(), fn (self $grade) => in_array($grade->level(), $levels, true)));
    }

    public function stage(): Stage
    {
        return Stage::from(explode('_', $this->value)[0] === 'media' ? 'media_general' : explode('_', $this->value)[0]);
    }

    /**
     * The code of its level in the catalog (SPEC-003).
     */
    public function level(): string
    {
        return match ($this->stage()) {
            Stage::Maternal, Stage::Preescolar => 'inicial',
            Stage::Primaria => 'primaria',
            Stage::MediaGeneral => 'media_general',
        };
    }

    public function number(): int
    {
        return (int) substr($this->value, -1);
    }

    public function name(): string
    {
        return match ($this->stage()) {
            Stage::Maternal => 'Maternal '.self::ROMANS[$this->number()],
            Stage::Preescolar => self::ORDINALS[$this->number()].' nivel',
            Stage::Primaria => self::ORDINALS[$this->number()].' grado',
            Stage::MediaGeneral => self::ORDINALS[$this->number()].' año',
        };
    }
}
