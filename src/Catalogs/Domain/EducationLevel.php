<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain;

/**
 * Levels of the Venezuelan school system (Subsistema de Educación Básica)
 * that the MPPE subjects catalog covers.
 */
enum EducationLevel: string
{
    case Inicial = 'inicial';
    case Primaria = 'primaria';
    case MediaGeneral = 'media_general';
}
