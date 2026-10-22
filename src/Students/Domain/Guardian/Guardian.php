<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Guardian;

/**
 * A guardian of the institution, shared by siblings (EST-R03.3, R03.8), and
 * the AulaX account linked to it, if any (EST-R08.1).
 */
final class Guardian
{
    private function __construct(
        private ?GuardianId $id,
        private GuardianData $data,
        private ?int $accountId,
        private ?int $pendingInvitationId,
    ) {}

    public static function register(GuardianData $data): self
    {
        return new self(null, $data, null, null);
    }

    public static function reconstitute(GuardianId $id, GuardianData $data, ?int $accountId, ?int $pendingInvitationId): self
    {
        return new self($id, $data, $accountId, $pendingInvitationId);
    }

    public function id(): ?GuardianId
    {
        return $this->id;
    }

    public function identify(GuardianId $id): void
    {
        $this->id = $id;
    }

    public function data(): GuardianData
    {
        return $this->data;
    }

    public function accountId(): ?int
    {
        return $this->accountId;
    }

    public function pendingInvitationId(): ?int
    {
        return $this->pendingInvitationId;
    }

    /**
     * New data for every student it represents (EST-R03.8). A corrected
     * cédula does not invalidate the cédulas escolares already formed with
     * the old one: those are checked when assigned.
     */
    public function correct(GuardianData $data): void
    {
        $this->data = $data;
    }
}
