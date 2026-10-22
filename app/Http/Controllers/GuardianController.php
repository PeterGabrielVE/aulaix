<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardianRequest;
use AulaX\Students\Application\FindGuardian\FindGuardianQuery;
use AulaX\Students\Application\UpdateGuardian\UpdateGuardianCommand;
use AulaX\Students\Application\UpdateGuardian\UpdateGuardianHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The guardians of the open institution (SPEC-009): finding one by its
 * document for the student form (EST-R03.3), and correcting it (EST-R03.8).
 */
class GuardianController extends Controller
{
    public function find(Request $request, FindGuardianQuery $findGuardian): JsonResponse
    {
        $guardian = $findGuardian((string) $request->query('document_type', ''), (string) $request->query('document', ''));

        return response()->json(['data' => $guardian === null ? null : [
            'id' => $guardian->id,
            'given_names' => $guardian->givenNames,
            'family_names' => $guardian->familyNames,
            'document_type' => $guardian->documentType,
            'document' => $guardian->document,
            'phone' => $guardian->phone,
            'email' => $guardian->email,
            'address' => $guardian->address,
        ]]);
    }

    public function update(GuardianRequest $request, int $guardian, UpdateGuardianHandler $updateGuardian): RedirectResponse
    {
        $updateGuardian(new UpdateGuardianCommand(
            $guardian,
            $request->validated('given_names'),
            $request->validated('family_names'),
            $request->validated('document_type'),
            $request->validated('document'),
            $request->validated('phone'),
            $request->validated('email'),
            $request->validated('address'),
        ));

        return back()->with('success', 'Se guardaron los datos del representante.');
    }
}
