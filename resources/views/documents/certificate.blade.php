<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate->kind->label() }}</title>
    @include('documents._styles')
</head>
<body>
    @include('documents._header', ['header' => $certificate->header])

    <div class="doc-title">{{ $certificate->kind->label() }}</div>

    <div class="prose">
        Quien suscribe, en representación de la Dirección de <strong>{{ $certificate->header->name }}</strong>
        @if ($certificate->header->deaCode)
            (Código DEA {{ $certificate->header->deaCode }})
        @endif
        , hace constar por medio de la presente que el(la) estudiante
        <strong>{{ $certificate->studentFullName }}</strong>, titular de la identificación
        <strong>{{ $certificate->studentIdentification }}</strong>:
    </div>

    <div class="prose">
        @if ($certificate->kind->value === 'study_certificate')
            cursa regularmente estudios en este plantel durante el año escolar
            <strong>{{ $certificate->schoolYearName }}</strong>, en
            <strong>{{ $certificate->gradeName }}</strong>@if ($certificate->sectionLetter), sección «<strong>{{ $certificate->sectionLetter }}</strong>»@endif.
        @elseif ($certificate->kind->value === 'enrollment_certificate')
            fue debidamente inscrito(a) en este plantel para el año escolar
            <strong>{{ $certificate->schoolYearName }}</strong>, en
            <strong>{{ $certificate->gradeName }}</strong>@if ($certificate->sectionLetter), sección «<strong>{{ $certificate->sectionLetter }}</strong>»@endif
            @if ($certificate->enrollmentDateDisplay)
                , en fecha <strong>{{ $certificate->enrollmentDateDisplay }}</strong>
            @endif.
        @elseif ($certificate->kind->value === 'withdrawal_certificate')
            fue retirado(a) formalmente de este plantel en fecha
            <strong>{{ $certificate->withdrawalDateDisplay }}</strong>, habiendo cursado
            <strong>{{ $certificate->gradeName }}</strong>@if ($certificate->sectionLetter), sección «<strong>{{ $certificate->sectionLetter }}</strong>»@endif,
            correspondiente al año escolar <strong>{{ $certificate->schoolYearName }}</strong>
            @if ($certificate->withdrawalReason)
                (motivo registrado: {{ $certificate->withdrawalReason }})
            @endif.
        @elseif ($certificate->kind->value === 'good_conduct_certificate')
            observó buena conducta durante su permanencia en este plantel, teniendo como referencia
            <strong>{{ $certificate->gradeName }}</strong>@if ($certificate->sectionLetter), sección «<strong>{{ $certificate->sectionLetter }}</strong>»@endif,
            del año escolar <strong>{{ $certificate->schoolYearName }}</strong>.
        @elseif ($certificate->kind->value === 'progression_certificate')
            culminó el año escolar <strong>{{ $certificate->schoolYearName }}</strong> en
            <strong>{{ $certificate->gradeName }}</strong>@if ($certificate->sectionLetter), sección «<strong>{{ $certificate->sectionLetter }}</strong>»@endif,
            obteniendo el resultado de <strong>{{ $certificate->progressionResultLabel }}</strong>.
        @endif
    </div>

    @if ($certificate->kind->value === 'good_conduct_certificate' && $certificate->goodConductNote)
        <div class="note-box">
            <strong>Observación:</strong> {!! nl2br(e($certificate->goodConductNote)) !!}
        </div>
    @endif

    <div class="prose">
        Constancia que se expide a solicitud de parte interesada en fecha
        <strong>{{ $certificate->issuedAtDisplay }}</strong>.
    </div>

    @include('documents._footer', [
        'header' => $certificate->header,
        'qrDataUri' => $certificate->qrDataUri,
        'verificationUrl' => $certificate->verificationUrl,
        'verificationCodeFormatted' => $certificate->verificationCodeFormatted,
        'issuedAtDisplay' => $certificate->issuedAtDisplay,
        'expiresAtDisplay' => $certificate->expiresAtDisplay,
    ])
</body>
</html>
