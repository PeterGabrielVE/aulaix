<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\Transfer\TransferList;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentGuardianRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentStudentDirectory;
use AulaX\Students\Infrastructure\Persistence\EloquentStudentRepository;
use AulaX\Students\Infrastructure\Persistence\EloquentTransferList;
use AulaX\Students\Infrastructure\Persistence\EloquentTransferRequestRepository;
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
    ];
}
