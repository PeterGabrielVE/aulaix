<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitutionRequest;
use AulaX\Tenancy\Application\ShowInstitution\ShowInstitutionHandler;
use AulaX\Tenancy\Application\ShowInstitution\SignatureView;
use AulaX\Tenancy\Application\UpdateInstitution\UpdateInstitutionCommand;
use AulaX\Tenancy\Application\UpdateInstitution\UpdateInstitutionHandler;
use AulaX\Tenancy\Domain\InstitutionDependency;
use AulaX\Tenancy\Domain\InstitutionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings of the open institution (SPEC-006): its details, type and
 * levels. Seeing them needs ver-plantel and changing them gestionar-plantel
 * (route middleware, and again in the use case); every rule lives in the
 * use cases.
 */
class InstitutionSettingsController extends Controller
{
    private const TYPE_NAMES = [
        'publica' => 'Público',
        'privada' => 'Privado',
        'subvencionada' => 'Subvencionado',
    ];

    private const DEPENDENCY_NAMES = [
        'nacional' => 'Nacional',
        'estadal' => 'Estadal',
        'municipal' => 'Municipal',
    ];

    public function show(Request $request, ShowInstitutionHandler $showInstitution): Response
    {
        $canManage = (bool) $request->user()->can('gestionar-plantel');
        $institution = $showInstitution(includeSignatures: $canManage);

        return Inertia::render('Institution/Settings', [
            'details' => [
                'name' => $institution->name,
                'subdomain' => $institution->subdomain,
                'deaCode' => $institution->deaCode,
                'rif' => $institution->rif,
                'location' => $institution->location,
                'address' => $institution->address,
                'phone' => $institution->phone,
                'email' => $institution->email,
                'type' => $institution->type,
                'dependency' => $institution->dependency,
                'levels' => $institution->levels,
                'hasLogo' => $institution->hasLogo,
                'signatures' => $institution->signatures === null ? null : array_map(fn (SignatureView $signature) => [
                    'role' => $signature->role,
                    'signerName' => $signature->signerName,
                    'signerTitle' => $signature->signerTitle,
                ], $institution->signatures),
            ],
            'options' => [
                'types' => array_map(fn (InstitutionType $type) => ['code' => $type->value, 'name' => self::TYPE_NAMES[$type->value]], InstitutionType::cases()),
                'dependencies' => array_map(fn (InstitutionDependency $dependency) => ['code' => $dependency->value, 'name' => self::DEPENDENCY_NAMES[$dependency->value]], InstitutionDependency::cases()),
                'levels' => $institution->levelOptions,
                'states' => $institution->states,
                'municipalities' => $institution->municipalities,
                'parishes' => $institution->parishes,
            ],
            'canManage' => $canManage,
        ]);
    }

    public function update(InstitutionRequest $request, UpdateInstitutionHandler $updateInstitution): RedirectResponse
    {
        $updateInstitution(new UpdateInstitutionCommand(
            name: $request->validated('name'),
            deaCode: $request->validated('dea_code'),
            rif: $request->validated('rif'),
            parishId: $request->validated('parish_id') === null ? null : (int) $request->validated('parish_id'),
            address: $request->validated('address'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            type: $request->validated('type'),
            levels: array_values($request->validated('levels')),
            dependency: $request->validated('dependency'),
        ));

        return back()->with('success', 'Se guardaron los datos del plantel.');
    }
}
