<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure;

use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Infrastructure\Auth\LaravelPasswordHasher;
use AulaX\IdentityAccess\Infrastructure\Notifications\NotificationInvitationMailer;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use AulaX\IdentityAccess\Infrastructure\Persistence\EloquentAccountRepository;
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
    ];

    public function boot(): void
    {
        // Stored in spatie/laravel-permission's model_type columns: a stable
        // alias, so moving the class never orphans role assignments again.
        Relation::morphMap(['user' => AccountModel::class]);
    }
}
