<table class="signatures-table">
    <tr>
        <td>
            @if ($header->directorSignature->imageDataUri)
                <img class="signature-img" src="{{ $header->directorSignature->imageDataUri }}" alt="Firma de Dirección">
            @else
                <div class="signature-spacer"></div>
            @endif
            <div class="signature-line">{{ $header->directorSignature->signerName ?: $header->directorSignature->defaultLabel }}</div>
            <div class="signature-title">{{ $header->directorSignature->signerTitle ?: 'Dirección del Plantel' }}</div>
        </td>
        @if ($header->secretarySignature)
            <td>
                @if ($header->secretarySignature->imageDataUri)
                    <img class="signature-img" src="{{ $header->secretarySignature->imageDataUri }}" alt="Firma de Secretaría">
                @else
                    <div class="signature-spacer"></div>
                @endif
                <div class="signature-line">{{ $header->secretarySignature->signerName ?: $header->secretarySignature->defaultLabel }}</div>
                <div class="signature-title">{{ $header->secretarySignature->signerTitle ?: 'Secretaría / Control de Estudios' }}</div>
            </td>
        @endif
    </tr>
</table>

<table class="verify-footer">
    <tr>
        <td class="verify-qr">
            <img src="{{ $qrDataUri }}" alt="Código QR de verificación">
        </td>
        <td class="verify-text">
            <div><strong>Verificación de autenticidad:</strong> escanee el código QR o ingrese en {{ $verificationUrl }}</div>
            <div>Código: <span class="verify-code">{{ $verificationCodeFormatted }}</span></div>
            <div>
                Emitido: {{ $issuedAtDisplay }}
                @if ($expiresAtDisplay)
                    &nbsp;·&nbsp; Válido hasta: {{ $expiresAtDisplay }}
                @endif
            </div>
        </td>
    </tr>
</table>
