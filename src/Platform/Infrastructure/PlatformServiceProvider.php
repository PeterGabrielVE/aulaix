<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Platform\Application\Port\InstitutionNotifier;
use AulaX\Platform\Application\Port\InstitutionUsage;
use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Application\Port\OperatorPasswords;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Application\Port\PendingTotpSecrets;
use AulaX\Platform\Application\Port\SecurityLog;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Infrastructure\Consolidation\IdentityConsolidationDesk;
use AulaX\Platform\Infrastructure\Institutions\MailInstitutionNotifier;
use AulaX\Platform\Infrastructure\Institutions\TenancyInstitutionDirectory;
use AulaX\Platform\Infrastructure\Institutions\TenantScopedInstitutionUsage;
use AulaX\Platform\Infrastructure\Notifications\NotificationOperatorMailer;
use AulaX\Platform\Infrastructure\Persistence\EloquentOperatorInvitationRepository;
use AulaX\Platform\Infrastructure\Persistence\EloquentOperatorRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Platform module's ports to their adapters (constitution A-07).
 */
final class PlatformServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        OperatorRepository::class => EloquentOperatorRepository::class,
        OperatorInvitationRepository::class => EloquentOperatorInvitationRepository::class,
        TotpCodes::class => Google2faTotpCodes::class,
        OperatorPasswords::class => LaravelOperatorPasswords::class,
        OperatorSession::class => LaravelOperatorSession::class,
        PendingTotpSecrets::class => SessionPendingTotpSecrets::class,
        OperatorMailer::class => NotificationOperatorMailer::class,
        SecurityLog::class => LaravelSecurityLog::class,
        InstitutionDirectory::class => TenancyInstitutionDirectory::class,
        InstitutionUsage::class => TenantScopedInstitutionUsage::class,
        InstitutionNotifier::class => MailInstitutionNotifier::class,
        ConsolidationDesk::class => IdentityConsolidationDesk::class,
    ];
}
