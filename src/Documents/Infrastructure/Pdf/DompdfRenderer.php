<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Pdf;

use AulaX\Documents\Application\DTO\CertificateData;
use AulaX\Documents\Application\DTO\ReportCardData;
use AulaX\Documents\Application\Port\PdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * Pure-PHP PDF renderer backed by dompdf/dompdf (ADR 0015, DOC-NF04):
 * - remote resources, embedded PHP and JavaScript are hard-disabled;
 * - chroot is locked to the application root;
 * - images (logo, signatures, QR) enter only as inline data URIs;
 * - all user-supplied text is escaped in Blade views via `{{ }}`.
 */
final readonly class DompdfRenderer implements PdfRenderer
{
    public function __construct(
        private ViewFactory $views,
        private string $chrootPath,
    ) {}

    public function renderReportCard(ReportCardData $data): string
    {
        return $this->renderHtml($this->views->make('documents.report-card', ['card' => $data])->render());
    }

    public function renderBatchReportCards(array $cards): string
    {
        return $this->renderHtml($this->views->make('documents.report-card-batch', ['cards' => $cards])->render());
    }

    public function renderCertificate(CertificateData $data): string
    {
        return $this->renderHtml($this->views->make('documents.certificate', ['certificate' => $data])->render());
    }

    private function renderHtml(string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot($this->chrootPath);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
