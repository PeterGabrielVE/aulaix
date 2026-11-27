<?php

namespace App\Http\Controllers;

use AulaX\Students\Application\Correction\CorrectionView;
use AulaX\Students\Application\Correction\ListCorrectionsQuery;
use AulaX\Students\Application\Correction\RequestCorrectionHandler;
use AulaX\Students\Application\Correction\ResolveCorrectionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corrections guardians ask for (SPEC-009 EST-R08.4, R08.5): sent from «Mis
 * representados», seen and attended by whoever manages students.
 */
class CorrectionController extends Controller
{
    public function index(ListCorrectionsQuery $listCorrections): Response
    {
        return Inertia::render('Corrections/Index', [
            'corrections' => array_map(self::present(...), $listCorrections()),
        ]);
    }

    public function store(Request $request, RequestCorrectionHandler $requestCorrection): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'text' => ['nullable', 'string', 'max:1000'],
        ]);

        $requestCorrection(
            (int) $request->user()->id,
            isset($data['student_id']) ? (int) $data['student_id'] : null,
            (string) ($data['text'] ?? ''),
        );

        return back()->with('success', 'Enviamos tu solicitud al plantel.');
    }

    public function resolve(int $correction, ResolveCorrectionHandler $resolveCorrection): RedirectResponse
    {
        $resolveCorrection($correction);

        return back()->with('success', 'La solicitud quedó atendida.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(CorrectionView $correction): array
    {
        return [
            'id' => $correction->id,
            'guardianName' => $correction->guardianName,
            'studentName' => $correction->studentName,
            'text' => $correction->text,
            'status' => $correction->status,
            'submittedOn' => $correction->submittedOn,
        ];
    }
}
