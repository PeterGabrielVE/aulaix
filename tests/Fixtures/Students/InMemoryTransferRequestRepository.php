<?php

namespace Tests\Fixtures\Students;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Transfer\TransferRequest;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Students\Domain\Transfer\TransferStatus;

/**
 * Transfer requests kept in memory, every one visible (the database's Row
 * Level Security is tested apart).
 */
final class InMemoryTransferRequestRepository implements TransferRequestRepository
{
    /** @var array<int, TransferRequest> */
    public array $requests = [];

    private int $nextId = 1;

    public function byId(TransferRequestId $id): ?TransferRequest
    {
        return isset($this->requests[$id->value]) ? clone $this->requests[$id->value] : null;
    }

    public function pendingOf(InstitutionId $destination, StudentIdentification $identification): ?TransferRequest
    {
        foreach ($this->requests as $request) {
            if ($request->destinationId()->equals($destination)
                && $request->identification()->value() === $identification->value()
                && $request->storedStatus() === TransferStatus::Pending) {
                return clone $request;
            }
        }

        return null;
    }

    public function save(TransferRequest $request): TransferRequestId
    {
        $id = $request->id() ?? TransferRequestId::fromInt($this->nextId++);
        $request->identify($id);
        $this->requests[$id->value] = clone $request;

        return $id;
    }
}
