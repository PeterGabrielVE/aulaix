@include('documents._header', ['header' => $card->header])

<div class="doc-title">{{ $card->title }}</div>

<table class="meta-table">
    <tr>
        <td class="meta-label">Estudiante</td>
        <td>{{ $card->studentFullName }}</td>
        <td class="meta-label">Identificación</td>
        <td>{{ $card->studentIdentification }}</td>
    </tr>
    <tr>
        <td class="meta-label">Año escolar</td>
        <td>{{ $card->schoolYearName }}</td>
        <td class="meta-label">Grado / Sección</td>
        <td>{{ $card->gradeName }} — Sección «{{ $card->sectionLetter }}»</td>
    </tr>
    <tr>
        <td class="meta-label">Período</td>
        <td colspan="3">{{ $card->periodLabel }}</td>
    </tr>
</table>

@if ($card->level === 'media_general')
    <table class="grades-table">
        <thead>
            <tr>
                <th>Área / Materia</th>
                @if ($card->isFinal)
                    <th class="num">1.er L</th>
                    <th class="num">2.º L</th>
                    <th class="num">3.er L</th>
                    <th class="num">Def.</th>
                    <th class="num">Rev.</th>
                    <th class="num">Def. Final</th>
                @else
                    <th class="num">Nota del lapso</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($card->subjects as $subject)
                <tr>
                    <td>{{ $subject['subject'] }}</td>
                    @if ($card->isFinal)
                        <td class="num">{{ $subject['term1'] ?? '—' }}</td>
                        <td class="num">{{ $subject['term2'] ?? '—' }}</td>
                        <td class="num">{{ $subject['term3'] ?? '—' }}</td>
                        <td class="num">{{ $subject['finalScore'] ?? '—' }}</td>
                        <td class="num">{{ $subject['revision'] ?? '—' }}</td>
                        <td class="num {{ $subject['failed'] ? 'failed' : '' }}">{{ $subject['finalAfterRevision'] ?? '—' }}</td>
                    @else
                        <td class="num {{ $subject['failed'] ? 'failed' : '' }}">{{ $subject['termScore'] ?? '—' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($card->isFinal && count($card->pendings) > 0)
        <div class="section-heading">Materias pendientes</div>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Grado de origen</th>
                    <th>Año de origen</th>
                    <th>Estado</th>
                    <th class="num">Definitiva</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($card->pendings as $pending)
                    <tr>
                        <td>{{ $pending['subject'] }}</td>
                        <td>{{ $pending['originGrade'] }}</td>
                        <td>{{ $pending['originYear'] }}</td>
                        <td>{{ $pending['standing'] }}</td>
                        <td class="num">{{ $pending['score'] !== null ? $pending['score'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@elseif ($card->level === 'primaria')
    @if ($card->isFinal)
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Período</th>
                    <th class="num">Literal</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['1' => '1.er lapso', '2' => '2.º lapso', '3' => '3.er lapso', 'final' => 'Literal final'] as $key => $label)
                    @php($mark = $card->primaryMarks[$key] ?? null)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="num">{{ $mark === null ? '—' : ($mark['incomplete'] ? 'Incompleto' : ($mark['literal'] ?? '—')) }}</td>
                        <td>{{ $mark === null ? '—' : ($mark['incomplete'] ? 'Incompleto' : ($mark['description'] ?? '')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        @php($mark = $card->primaryMarks[(string) $card->term] ?? null)
        <div class="box">
            <div><strong>Literal del lapso:</strong> {{ $mark === null || $mark['incomplete'] ? 'Incompleto' : ($mark['literal'] ?? 'Incompleto') }}</div>
            @if ($mark && ! $mark['incomplete'] && $mark['description'])
                <div style="margin-top: 6px;">{!! nl2br(e($mark['description'])) !!}</div>
            @endif
        </div>
    @endif
@else
    @if ($card->isFinal)
        @foreach (['1' => '1.er lapso', '2' => '2.º lapso', '3' => '3.er lapso'] as $key => $label)
            @php($report = $card->initialReports[$key] ?? null)
            <div class="section-heading">{{ $label }}</div>
            <div class="box">
                @if ($report === null || $report['incomplete'] || ! $report['description'])
                    Incompleto
                @else
                    {!! nl2br(e($report['description'])) !!}
                @endif
            </div>
        @endforeach
    @else
        @php($report = $card->initialReports[(string) $card->term] ?? null)
        <div class="section-heading">Informe descriptivo</div>
        <div class="box">
            @if ($report === null || $report['incomplete'] || ! $report['description'])
                Incompleto
            @else
                {!! nl2br(e($report['description'])) !!}
            @endif
        </div>
    @endif
@endif

@if ($card->isFinal && $card->progressionLabel)
    <div class="box">
        <strong>Resultado de prosecución:</strong> {{ $card->progressionLabel }}
    </div>
@endif

@include('documents._footer', [
    'header' => $card->header,
    'qrDataUri' => $card->qrDataUri,
    'verificationUrl' => $card->verificationUrl,
    'verificationCodeFormatted' => $card->verificationCodeFormatted,
    'issuedAtDisplay' => $card->issuedAtDisplay,
    'expiresAtDisplay' => null,
])
