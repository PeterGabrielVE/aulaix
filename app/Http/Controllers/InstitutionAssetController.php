<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitutionImageRequest;
use AulaX\Tenancy\Application\ChangeLogo\ChangeLogoHandler;
use AulaX\Tenancy\Application\ChangeSignature\ChangeSignatureCommand;
use AulaX\Tenancy\Application\ChangeSignature\ChangeSignatureHandler;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\InstitutionAssets\InstitutionAssetsQuery;
use AulaX\Tenancy\Application\RemoveLogo\RemoveLogoHandler;
use AulaX\Tenancy\Application\RemoveSignature\RemoveSignatureHandler;
use AulaX\Tenancy\Domain\SignatureRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The logo and the signatures of the open institution (SPEC-006 INS-R04).
 * They are served only from here, to whoever the route lets in, never from
 * a disk URL (INS-R04.8).
 */
class InstitutionAssetController extends Controller
{
    public function logo(InstitutionAssetsQuery $assets): Response
    {
        return $this->image($assets, AssetKind::Logo);
    }

    public function signature(SignatureRole $role, InstitutionAssetsQuery $assets): Response
    {
        return $this->image($assets, AssetKind::signatureOf($role));
    }

    public function storeLogo(InstitutionImageRequest $request, ChangeLogoHandler $changeLogo): RedirectResponse
    {
        $changeLogo($request->upload());

        return back()->with('success', 'Se guardó el logo.');
    }

    public function destroyLogo(RemoveLogoHandler $removeLogo): RedirectResponse
    {
        $removeLogo();

        return back()->with('success', 'Se quitó el logo.');
    }

    public function storeSignature(InstitutionImageRequest $request, SignatureRole $role, ChangeSignatureHandler $changeSignature): RedirectResponse
    {
        $changeSignature(new ChangeSignatureCommand(
            $role,
            $request->validated('signer_name'),
            $request->validated('signer_title'),
            $request->upload(),
        ));

        return back()->with('success', 'Se guardó la firma.');
    }

    public function destroySignature(SignatureRole $role, RemoveSignatureHandler $removeSignature): RedirectResponse
    {
        $removeSignature($role);

        return back()->with('success', 'Se quitó la firma.');
    }

    /**
     * The stored type, never sniffed nor cached by anyone but the browser.
     */
    private function image(InstitutionAssetsQuery $assets, AssetKind $kind): Response
    {
        $image = $assets($kind) ?? abort(404);

        return response($image->contents, 200, [
            'Content-Type' => $image->mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
