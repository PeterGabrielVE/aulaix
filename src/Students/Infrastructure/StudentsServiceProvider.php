<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\Students\Application\Correction\CorrectionList;
use AulaX\Students\Application\GuardianAccount\LinkGuardianAccountHandler;
use AulaX\Students\Application\GuardianAccount\SoleLegalGuardianBlocker;
use AulaX\Students\Application\Port\GuardianInvitations;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\Transfer\TransferList;
use AulaX\Students\Domain\Correction\CorrectionRequestRepository;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentCorrectionList;
use AulaX\Students\Infrastructure\Persistence\EloquentCorrectionRequestRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentGuardianRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentStudentDirectory;
use AulaX\Students\Infrastructure\Persistence\EloquentStudentRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentTransferList;
use AulaX\Students\Infrastructure\Persistence\EloquentTransferRequestRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Students module's ports to their adapters (constitution A-07).
 */
final class StudentsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        StudentRepository::class => EloquentStudentRepository::class,
        GuardianRepository::class => EloquentGuardianRepository::class,
        StudentPermissions::class => GateStudentPermissions::class,
        StudentDirectory::class => EloquentStudentDirectory::class,
        TransferCopier::class => TenantSwitchingTransferCopier::class,
        TransferRequestRepository::class => EloquentTransferRequestRepository::class,
        TransferList::class => EloquentTransferList::class,
        GuardianInvitations::class => IdentityAccessGuardianInvitations::class,
        CorrectionRequestRepository::class => EloquentCorrectionRequestRepository::class,
        CorrectionList::class => EloquentCorrectionList::class,
    ];

    public function register(): void
    {
        // EST-R08.6: IdentityAccess's extension point for account deletion (IDG-R08.3).
        $this->app->tag([SoleLegalGuardianBlocker::class], DeletionBlocker::TAG);
    }

    public function boot(): void
    {
        // EST-R08.1: an accepted invitation sent for a guardian links its account.
        Event::listen(MemberJoined::class, [LinkGuardianAccountHandler::class, '__invoke']);
    }
}
