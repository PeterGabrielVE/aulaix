<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

use AulaX\Documents\Application\DTO\CertificateData;
use AulaX\Documents\Application\DTO\ReportCardData;

/**
 * Renders report cards and certificates to PDF bytes (ADR 0015).
 */
interface PdfRenderer
{
    public function renderReportCard(ReportCardData $data): string;

    /**
     * @param  non-empty-list<ReportCardData>  $cards
     */
    public function renderBatchReportCards(array $cards): string;

    public function renderCertificate(CertificateData $data): string;
}
