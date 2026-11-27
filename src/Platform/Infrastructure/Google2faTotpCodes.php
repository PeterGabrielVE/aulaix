<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\TotpCodes;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use DateTimeImmutable;
use PragmaRX\Google2FA\Google2FA;

/**
 * pragmarx/google2fa for the codes and bacon/bacon-qr-code for the QR, as
 * Laravel Fortify does (ADR 0010). The time comes from the caller's clock.
 */
final readonly class Google2faTotpCodes implements TotpCodes
{
    private const int STEP_SECONDS = 30;

    private const int WINDOW = 1;

    private const string ISSUER = 'AulaX Plataforma';

    public function __construct(private Google2FA $google2fa) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function verify(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, DateTimeImmutable $now): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $current = intdiv($now->getTimestamp(), self::STEP_SECONDS);

        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if (hash_equals($this->google2fa->oathTotp($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function qrSvg(string $accountLabel, #[\SensitiveParameter] string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(self::ISSUER, $accountLabel, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }
}
