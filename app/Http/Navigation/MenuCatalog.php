<?php

namespace App\Http\Navigation;

use AulaX\Workspace\Application\ShowLayout\LayoutView;
use Illuminate\Http\Request;

/**
 * How each menu entry of Workspace (SPEC-013) is presented: its route, text,
 * shortcut description (NAV-R04.9) and the routes it counts as current
 * (NAV-R01.4). Who sees an entry is Workspace's call, not this catalog's.
 */
final class MenuCatalog
{
    /**
     * @var array<string, array{route: string, label: string, description: string, current: list<string>}>
     */
    private const ENTRIES = [
        'home' => ['route' => 'dashboard', 'label' => 'Inicio', 'description' => 'Tus pendientes y accesos del día.', 'current' => ['*.home', 'dashboard']],
        'calendar' => ['route' => 'calendar.index', 'label' => 'Año escolar', 'description' => 'Planifica el año, sus lapsos y su cierre.', 'current' => ['calendar.*']],
        'structure' => ['route' => 'structure.show', 'label' => 'Estructura', 'description' => 'Secciones, pensum, docentes y coordinadores del año.', 'current' => ['structure.*']],
        'my_assignments' => ['route' => 'my-assignments', 'label' => 'Mis asignaciones', 'description' => 'Las materias que dictas y las secciones que guías.', 'current' => ['my-assignments']],
        'grades' => ['route' => 'grades.index', 'label' => 'Notas', 'description' => 'Planes de evaluación y notas de cada lapso.', 'current' => ['grades.*']],
        'students' => ['route' => 'students.index', 'label' => 'Estudiantes', 'description' => 'Fichas de los estudiantes y sus representantes.', 'current' => ['students.*', 'guardians.*']],
        'enrollments' => ['route' => 'enrollments.index', 'label' => 'Inscripciones', 'description' => 'Secciones, inscritos, prosecución y el año siguiente.', 'current' => ['enrollments.*', 'promotion.*', 'next-year.*']],
        'transfers' => ['route' => 'transfers.index', 'label' => 'Traslados', 'description' => 'Solicitudes de traslado entre planteles.', 'current' => ['transfers.*']],
        'corrections' => ['route' => 'corrections.index', 'label' => 'Correcciones', 'description' => 'Correcciones que piden los representantes.', 'current' => ['corrections.*']],
        'my_students' => ['route' => 'my-students.index', 'label' => 'Mis representados', 'description' => 'Los datos de tus representados en el plantel.', 'current' => ['my-students.*']],
        'institution' => ['route' => 'institution.show', 'label' => 'Plantel', 'description' => 'Datos, niveles, logo y firmas del plantel.', 'current' => ['institution.*']],
        'users' => ['route' => 'users.index', 'label' => 'Usuarios', 'description' => 'Personal del plantel, roles e invitaciones.', 'current' => ['users.*', 'invitations.*']],
        'audit' => ['route' => 'audit.index', 'label' => 'Bitácora', 'description' => 'Quién cambió qué y cuándo.', 'current' => ['audit.*']],
    ];

    private const GROUPS = [
        'academic' => 'Académico',
        'students' => 'Estudiantes',
        'institution' => 'Plantel',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ENTRIES);
    }

    public static function routeOf(string $key): string
    {
        return self::ENTRIES[$key]['route'];
    }

    /**
     * @return array{key: string, label: string, description: string, href: string}
     */
    public static function shortcut(string $key): array
    {
        $entry = self::ENTRIES[$key];

        return ['key' => $key, 'label' => $entry['label'], 'description' => $entry['description'], 'href' => route($entry['route'])];
    }

    /**
     * The menu of the layout, ready for the page.
     *
     * @return list<array{group: ?string, label: ?string, entries: list<array{key: string, label: string, href: string, active: bool}>}>
     */
    public static function present(LayoutView $layout, Request $request): array
    {
        return array_map(fn (array $group) => [
            'group' => $group['group'],
            'label' => $group['group'] === null ? null : self::GROUPS[$group['group']],
            'entries' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => self::ENTRIES[$key]['label'],
                'href' => route(self::ENTRIES[$key]['route']),
                'active' => $request->routeIs(...self::ENTRIES[$key]['current']),
            ], $group['entries']),
        ], $layout->navigation);
    }
}
