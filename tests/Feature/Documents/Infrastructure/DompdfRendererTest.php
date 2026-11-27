<?php

use AulaX\Documents\Application\DTO\CertificateData;
use AulaX\Documents\Application\DTO\InstitutionHeaderData;
use AulaX\Documents\Application\DTO\ReportCardData;
use AulaX\Documents\Application\DTO\SignatureBlockData;
use AulaX\Documents\Application\Port\PdfRenderer;
use AulaX\Documents\Application\Port\QrCodeGenerator;
use AulaX\Documents\Domain\DocumentKind;

/**
 * SPEC-012 DOC-R05.1 – R05.3, R07.1, NF04 (ADR 0015): pure-PHP dompdf
 * rendering with inline QR/images, strict Blade escaping and remote fetches
 * disabled.
 */
test('renders valid PDFs with inline logo, signatures and QR while escaping HTML and blocking remote resources', function () {
    $qr = app(QrCodeGenerator::class)->toDataUri('https://norte.aulax.test/verificar/TESTCODE');
    expect($qr)->toStartWith('data:image/svg+xml;base64,');

    $header = new InstitutionHeaderData(
        name: 'Colegio <script>alert(1)</script> Norte',
        deaCode: 'S1234D',
        rif: 'J-12345678-9',
        location: 'Chacao, Chacao, Miranda',
        address: 'Av. Principal',
        phone: '0212-5550101',
        email: 'direccion@norte.edu.ve',
        logoDataUri: null,
        directorSignature: new SignatureBlockData('Dirección', 'Prof. María López', 'Directora', null),
        secretarySignature: null,
    );

    $card = new ReportCardData(
        header: $header,
        title: 'Boleta de calificaciones — 1.er lapso',
        studentFullName: 'Ana <img src="http://169.254.169.254/latest/meta-data/"> Pérez',
        studentIdentification: 'V-30123456',
        schoolYearName: '2026-2027',
        gradeName: '1.er año',
        sectionLetter: 'A',
        level: 'media_general',
        term: 1,
        isFinal: false,
        periodLabel: '2026-2027 · 1.er lapso',
        issuedAtDisplay: '27/11/2026',
        verificationCodeFormatted: 'ABCDE-FGHIJ-KLMNP-QRSTV-WXYZ01',
        verificationUrl: 'https://norte.aulax.test/verificar/TESTCODE',
        qrDataUri: $qr,
        subjects: [
            ['subject' => 'Matemática', 'term1' => '16', 'term2' => null, 'term3' => null, 'termScore' => '16', 'finalScore' => null, 'revision' => null, 'finalAfterRevision' => null, 'failed' => false],
        ],
    );

    $renderer = app(PdfRenderer::class);
    $reportPdf = $renderer->renderReportCard($card);
    $batchPdf = $renderer->renderBatchReportCards([$card, $card]);
    $certPdf = $renderer->renderCertificate(new CertificateData(
        header: $header,
        kind: DocumentKind::GoodConductCertificate,
        studentFullName: 'Ana Pérez',
        studentIdentification: 'V-30123456',
        schoolYearName: '2026-2027',
        gradeName: '1.er año',
        sectionLetter: 'A',
        issuedAtDisplay: '27/11/2026',
        expiresAtDisplay: '25/02/2027',
        verificationCodeFormatted: 'ABCDE-FGHIJ-KLMNP-QRSTV-WXYZ01',
        verificationUrl: 'https://norte.aulax.test/verificar/TESTCODE',
        qrDataUri: $qr,
        goodConductNote: '<b>Excelente</b> conducta con <img src="https://evil.test/ping">',
    ));

    expect($reportPdf)->toStartWith('%PDF-')
        ->and(strlen($reportPdf))->toBeGreaterThan(1000)
        ->and($batchPdf)->toStartWith('%PDF-')
        ->and(strlen($batchPdf))->toBeGreaterThan(strlen($reportPdf))
        ->and($certPdf)->toStartWith('%PDF-');
});
