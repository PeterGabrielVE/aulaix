<?php

namespace App\Http\Controllers;

use AulaX\Students\Application\Transfer\ApproveTransferHandler;
use AulaX\Students\Application\Transfer\ListTransfersQuery;
use AulaX\Students\Application\Transfer\RejectTransferHandler;
use AulaX\Students\Application\Transfer\RequestTransferHandler;
use AulaX\Students\Application\Transfer\TransferView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transfers between AulaX institutions (SPEC-009 EST-R07, ADR 0008). Needs
 * gestionar-estudiantes (route middleware, and again in the use cases);
 * asking is rate limited per institution (EST-NF04).
 */
class TransferController extends Controller
{
    public function index(ListTransfersQuery $listTransfers): Response
    {
        $board = $listTransfers();

        return Inertia::render('Transfers/Index', [
            'incoming' => array_map(self::present(...), $board->incoming),
            'outgoing' => array_map(self::present(...), $board->outgoing),
        ]);
    }

    public function store(Request $request, RequestTransferHandler $requestTransfer): RedirectResponse
    {
        $data = $request->validate([
            'identification_type' => ['nullable', 'string', 'max:16'],
            'identification' => ['nullable', 'string', 'max:20'],
        ]);

        $requestTransfer((string) ($data['identification_type'] ?? ''), (string) ($data['identification'] ?? ''));

        // The same answer whether or not some institution has the student (EST-R07.2).
        return back()->with('success', 'Enviamos la solicitud. Si el estudiante está activo en otro plantel de AulaX, ese plantel la responderá.');
    }

    public function approve(int $transfer, ApproveTransferHandler $approveTransfer): RedirectResponse
    {
        $approveTransfer($transfer);

        return back()->with('success', 'Aprobaste el traslado: el estudiante quedó retirado aquí y registrado en el plantel destino.');
    }

    public function reject(Request $request, int $transfer, RejectTransferHandler $rejectTransfer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $rejectTransfer($transfer, (string) ($data['reason'] ?? ''));

        return back()->with('success', 'Rechazaste la solicitud de traslado.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(TransferView $transfer): array
    {
        return [
            'id' => $transfer->id,
            'identification' => $transfer->identification,
            'studentName' => $transfer->studentName,
            'destinationName' => $transfer->destinationName,
            'originName' => $transfer->originName,
            'status' => $transfer->status,
            'requestedOn' => $transfer->requestedOn,
            'lastDayToAnswer' => $transfer->lastDayToAnswer,
            'rejectionReason' => $transfer->rejectionReason,
        ];
    }
}
