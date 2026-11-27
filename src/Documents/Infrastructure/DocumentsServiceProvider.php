<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure;

use AulaX\Documents\Application\Port\AcademicFacts;
use AulaX\Documents\Application\Port\DocumentHistoryDirectory;
use AulaX\Documents\Application\Port\DocumentPermissions;
use AulaX\Documents\Application\Port\DocumentStorage;
use AulaX\Documents\Application\Port\EvaluationFacts;
use AulaX\Documents\Application\Port\InstitutionFacts;
use AulaX\Documents\Application\Port\PdfRenderer;
use AulaX\Documents\Application\Port\QrCodeGenerator;
use AulaX\Documents\Application\Port\StudentRecordFacts;
use AulaX\Documents\Domain\IssuedDocumentRepository;
use AulaX\Documents\Infrastructure\Facts\SchoolAcademicFacts;
use AulaX\Documents\Infrastructure\Facts\SchoolEvaluationFacts;
use AulaX\Documents\Infrastructure\Facts\SchoolStudentRecordFacts;
use AulaX\Documents\Infrastructure\Facts\TenancyInstitutionFacts;
use AulaX\Documents\Infrastructure\Pdf\BaconQrCodeGenerator;
use AulaX\Documents\Infrastructure\Pdf\DompdfRenderer;
use AulaX\Documents\Infrastructure\Persistence\EloquentDocumentHistoryDirectory;
use AulaX\Documents\Infrastructure\Persistence\EloquentIssuedDocumentRepository;
use AulaX\Documents\Infrastructure\Storage\LaravelDocumentStorage;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionAssets\InstitutionAssetsQuery;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionProfileQuery;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Documents module's ports to their adapters (constitution A-07,
 * SPEC-012, ADR 0015).
 */
final class DocumentsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        IssuedDocumentRepository::class => EloquentIssuedDocumentRepository::class,
        DocumentHistoryDirectory::class => EloquentDocumentHistoryDirectory::class,
        DocumentPermissions::class => GateDocumentPermissions::class,
        QrCodeGenerator::class => BaconQrCodeGenerator::class,
        AcademicFacts::class => SchoolAcademicFacts::class,
        StudentRecordFacts::class => SchoolStudentRecordFacts::class,
        EvaluationFacts::class => SchoolEvaluationFacts::class,
    ];

    public function register(): void
    {
        $this->app->bind(DocumentStorage::class, fn ($app) => new LaravelDocumentStorage(
            $app->make('filesystem')->disk((string) $app->make(Config::class)->get('tenancy.files_disk')),
            $app->make(CurrentTenant::class),
        ));

        $this->app->bind(PdfRenderer::class, fn ($app) => new DompdfRenderer(
            $app->make(ViewFactory::class),
            $app->basePath(),
        ));

        $this->app->bind(InstitutionFacts::class, function ($app) {
            /** @var Config $config */
            $config = $app->make(Config::class);
            $url = (string) $config->get('app.url', 'https://localhost');
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';

            return new TenancyInstitutionFacts(
                $app->make(InstitutionProfileQuery::class),
                $app->make(InstitutionAssetsQuery::class),
                (string) $config->get('app.domain'),
                $scheme,
            );
        });
    }
}
