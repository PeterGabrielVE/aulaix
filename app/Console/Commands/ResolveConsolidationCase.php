<?php

namespace App\Console\Commands;

use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
use Illuminate\Console\Command;

/**
 * SPEC-004 IDG-R11.2 – R11.7: support resolves one case marked for review.
 * Only an input to the use case, like the future super administration panel
 * (IDG-NF07).
 */
class ResolveConsolidationCase extends Command
{
    protected $signature = 'identity:consolidation:resolve
        {accounts* : Los ids de todas las cuentas del caso}
        {--operator= : Quién resuelve (debe estar en IDENTITY_CONSOLIDATION_OPERATORS)}
        {--decision= : consolidate o separate}
        {--reason= : El motivo de la decisión}
        {--verification-ref= : Referencia del procedimiento que verificó la titularidad (obligatoria para consolidar)}
        {--dry-run : Muestra los cambios sin aplicarlos}';

    protected $description = 'Resuelve un caso de consolidación de cuentas marcado para revisión';

    public function handle(ResolveConsolidationCaseHandler $resolveCase): int
    {
        $decision = ConsolidationDecision::tryFrom((string) $this->option('decision'));

        if ($decision === null) {
            $this->error('--decision debe ser consolidate o separate.');

            return self::FAILURE;
        }

        try {
            $resolution = $resolveCase(new ResolveConsolidationCaseCommand(
                (string) $this->option('operator'),
                array_map('intval', (array) $this->argument('accounts')),
                $decision,
                (string) $this->option('reason'),
                $this->option('verification-ref'),
                (bool) $this->option('dry-run'),
            ));
        } catch (CaseResolutionRefused $refused) {
            $this->error($refused->getMessage().' No se aplicó ningún cambio.');

            return self::FAILURE;
        }

        $affected = implode(', ', array_map(fn (int $id) => "#{$id}", $resolution->affectedIds));
        $plan = $resolution->decision === ConsolidationDecision::Consolidate
            ? "Consolidar en #{$resolution->survivorId}: se mueven sus planteles y roles y se borran {$affected}."
            : "Mantener separadas {$affected}: no se unificarán nunca.";

        $this->line($resolution->applied ? 'Hecho.' : 'Simulación: no se aplicó ningún cambio.');
        $this->line($plan);

        return self::SUCCESS;
    }
}
