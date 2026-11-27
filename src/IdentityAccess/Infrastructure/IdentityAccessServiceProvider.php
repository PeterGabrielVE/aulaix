<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure;

use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
use AulaX\IdentityAccess\Application\Account\DeletionBlockers;
use AulaX\IdentityAccess\Application\Account\LastAdministratorBlocker;
use AulaX\IdentityAccess\Application\Auth\AccountInstitutions;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationCases;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationOperators;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\AccountMerger;
use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\BrowserBinding;
use AulaX\IdentityAccess\Application\Port\ConsolidationCodes;
use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Application\Port\LoginThrottle;
use AulaX\IdentityAccess\Application\Port\MembershipSession;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Application\Port\PasswordResetTokens;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Infrastructure\Auth\BrokerPasswordResetTokens;
use AulaX\IdentityAccess\Infrastructure\Auth\CacheConsolidationCodes;
use AulaX\IdentityAccess\Infrastructure\Auth\CacheHandoffTokens;
use AulaX\IdentityAccess\Infrastructure\Auth\CookieBrowserBinding;
use AulaX\IdentityAccess\Infrastructure\Auth\DatabaseSessionRevoker;
use AulaX\IdentityAccess\Infrastructure\Auth\LaravelAuthenticator;
use AulaX\IdentityAccess\Infrastructure\Auth\LaravelMembershipSession;
use AulaX\IdentityAccess\Infrastructure\Auth\LaravelPasswordHasher;
use AulaX\IdentityAccess\Infrastructure\Auth\RateLimiterLoginThrottle;
use AulaX\IdentityAccess\Infrastructure\Notifications\NotificationAccountMailer;
use AulaX\IdentityAccess\Infrastructure\Notifications\NotificationInvitationMailer;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentAccountInstitutions;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentAccountMerger;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentAccountRepository;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentConsolidationCases;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentInstitutionScope;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentInvitationRepository;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentMemberDirectory;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentMembershipRepository;
use AulaX\IdentityAccess\Infrastructure\Persistence\SpatieRoleCatalog;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the IdentityAccess module's ports to their adapters (constitution A-07).
 */
final class IdentityAccessServiceProvider extends ServiceProvider
{
    public const DELETION_BLOCKERS = DeletionBlocker::TAG;

    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AccountRepository::class => EloquentAccountRepository::class,
        MembershipRepository::class => EloquentMembershipRepository::class,
        InvitationRepository::class => EloquentInvitationRepository::class,
        RoleCatalog::class => SpatieRoleCatalog::class,
        MemberDirectory::class => EloquentMemberDirectory::class,
        PasswordHasher::class => LaravelPasswordHasher::class,
        InvitationMailer::class => NotificationInvitationMailer::class,
        Authenticator::class => LaravelAuthenticator::class,
        AccountInstitutions::class => EloquentAccountInstitutions::class,
        HandoffTokens::class => CacheHandoffTokens::class,
        BrowserBinding::class => CookieBrowserBinding::class,
        MembershipSession::class => LaravelMembershipSession::class,
        LoginThrottle::class => RateLimiterLoginThrottle::class,
        SessionRevoker::class => DatabaseSessionRevoker::class,
        PasswordResetTokens::class => BrokerPasswordResetTokens::class,
        AccountMailer::class => NotificationAccountMailer::class,
        ConsolidationCodes::class => CacheConsolidationCodes::class,
        AccountMerger::class => EloquentAccountMerger::class,
        ConsolidationCases::class => EloquentConsolidationCases::class,
        ConsolidationOperators::class => PlatformConsolidationOperators::class,
        InstitutionScope::class => EloquentInstitutionScope::class,
    ];

    public function register(): void
    {
        // Extension point (IDG-R08.3, R08.4): other modules tag their own
        // DeletionBlocker with this tag.
        $this->app->tag([LastAdministratorBlocker::class], self::DELETION_BLOCKERS);
        $this->app->when(DeletionBlockers::class)
            ->needs('$blockers')
            ->giveTagged(self::DELETION_BLOCKERS);
    }

    public function boot(): void
    {
        // Stored in spatie/laravel-permission's model_type columns: a stable
        // alias, so moving the class never orphans role assignments again.
        Relation::morphMap(['user' => AccountModel::class]);
    }
}
