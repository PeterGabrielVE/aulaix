<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use AulaX\Platform\Application\Consolidation\ListCasesQuery;
use AulaX\Platform\Application\Consolidation\PreviewResolutionQuery;
use AulaX\Platform\Application\Consolidation\ResolveCaseHandler;
use AulaX\Platform\Application\Consolidation\ShowCaseQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The doubtful consolidation cases (SPEC-014 PLT-R06), for every operator:
 * a preview first (PLT-R06.3), then the resolution, which needs a fresh
 * TOTP because it merges or splits accounts for good.
 */
class ConsolidationController extends Controller
{
    public function index(ListCasesQuery $listCases): Response
    {
        return Inertia::render('Platform/Consolidations', ['cases' => $listCases()]);
    }

    public function show(Request $request, int $account, ShowCaseQuery $showCase): Response
    {
        return Inertia::render('Platform/Consolidation', [
            'case' => $showCase($account),
            'preview' => $request->session()->get('platform.consolidation_preview'),
        ]);
    }

    public function preview(Request $request, PreviewResolutionQuery $previewResolution): RedirectResponse
    {
        $input = $this->validated($request);
        $plan = $previewResolution($input['accounts'], $input['decision'], $input['reason'], $input['verification_ref']);

        return back()->with('platform.consolidation_preview', ['plan' => $plan, 'input' => $input]);
    }

    public function resolve(Request $request, ResolveCaseHandler $resolveCase): RedirectResponse
    {
        $input = $this->validated($request);
        $resolution = $resolveCase($input['accounts'], $input['decision'], $input['reason'], $input['verification_ref']);

        return redirect()->route('platform.consolidations')->with('success', $resolution['decision'] === 'consolidate'
            ? "Se consolidaron las cuentas en la #{$resolution['survivorId']}."
            : 'Las cuentas quedan separadas para siempre.');
    }

    /**
     * @return array{accounts: list<int>, decision: string, reason: string, verification_ref: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'accounts' => ['required', 'array', 'min:2', 'max:20'],
            'accounts.*' => ['integer'],
            'decision' => ['required', Rule::in(['consolidate', 'separate'])],
            'reason' => ['required', 'string', 'max:500'],
            'verification_ref' => ['nullable', 'string', 'max:120'],
        ], [], ['reason' => 'motivo', 'decision' => 'decisión', 'verification_ref' => 'referencia de la verificación']);

        return [
            'accounts' => array_values(array_map('intval', $data['accounts'])),
            'decision' => $data['decision'],
            'reason' => $data['reason'],
            'verification_ref' => $data['verification_ref'] ?? null,
        ];
    }
}
