<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class CentralHome
{
    /**
     * @param  list<InstitutionChoice>  $institutions  Empty for the welcome screen (IDG-R03.6).
     * @param  list<PendingInvitationView>  $invitations
     * @param  bool  $canConsolidate  The account shares its email with other historical accounts it may merge (IDG-R09.3).
     */
    public function __construct(
        public string $name,
        public array $institutions,
        public array $invitations,
        public bool $canConsolidate,
        public bool $pendingDeletion = false,
    ) {}
}
