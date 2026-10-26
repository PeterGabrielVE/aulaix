<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Institutions;

use AulaX\IdentityAccess\Application\Members\AdministratorContactsQuery;
use AulaX\Platform\Application\Port\InstitutionNotifier;
use AulaX\Platform\Infrastructure\TenantScopedReader;
use AulaX\Tenancy\Application\Administration\ShowInstitutionQuery;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells an institution's active Administradores that it was deactivated or
 * reactivated (SPEC-014 PLT-R05.3). Their addresses are read within the
 * institution's RLS by the bounded reader, even when it is inactive, and the
 * mail is queued afterwards. A failure is reported, never thrown: the
 * status has already changed.
 */
final readonly class MailInstitutionNotifier implements InstitutionNotifier
{
    public function __construct(
        private TenantScopedReader $reader,
        private AdministratorContactsQuery $administradores,
        private ShowInstitutionQuery $institutions,
    ) {}

    public function statusChanged(int $institutionId, bool $active, string $reason): void
    {
        try {
            $institution = ($this->institutions)($institutionId)['name'] ?? '';
            $contacts = $this->reader->read($institutionId, fn () => ($this->administradores)());

            foreach ($contacts as $contact) {
                Notification::route('mail', $contact['email'])
                    ->notify(new InstitutionStatusMail($contact['name'], $institution, $active, $reason));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
