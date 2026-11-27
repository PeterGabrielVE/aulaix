<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ChangeSignature;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\ChangeSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\FileReplacement;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Application\SignatureAudit;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;

/**
 * The manager uploads the signature of the Director or the Secretary, with
 * who signs and their title, replacing the previous one of that role
 * (INS-R04.2). The audit log keeps who signs, never the image (INS-R06.2).
 */
final readonly class ChangeSignatureHandler
{
    public function __construct(
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
        private InstitutionFiles $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(ChangeSignatureCommand $command): void
    {
        OpenInstitution::forManagement($this->tenant, $this->permissions);
        $path = $this->files->store(AssetKind::signatureOf($command->role), $command->image);

        FileReplacement::commit($this->files, $path, fn () => $this->transactions->run(function () use ($command, $path) {
            $signature = Signature::of($command->role, $command->signerName, $command->signerTitle, $path);
            $previous = $this->signatures->of($command->role);

            $this->signatures->save($signature);
            $this->audit->record(AuditEvent::of(
                'tenancy.signature.changed',
                SignatureAudit::subject($command->role),
                ChangeSet::diff(SignatureAudit::signer($previous), SignatureAudit::signer($signature)),
            ));

            return $previous?->filePath;
        }));
    }
}
