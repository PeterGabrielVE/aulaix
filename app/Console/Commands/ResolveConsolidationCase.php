<?php

namespace App\Console\Commands;

use AulaX\Audit\Application\AuditContext;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
use Illuminate\Console\Command;

/**
 * SPEC-004 IDG-R11.2 – R11.7: support resolves one case marked for review.
 * Only an input to the use case, like the platform panel (IDG-NF07); the
 * operator must be an active operator of the panel (SPEC-014 PLT-R06.6).
 */
class ResolveConsolidationCase extends Command
{
    protected $signature = 'identity:consolidation:resolve
        {accounts* : Los ids de todas las cuentas del caso}
        {--operator= : Correo de quien resuelve (un operador activo del panel de plataforma)}
        {--decision= : consolidate o separate}
        {--reason= : El motivo de la decisión}
        {--verification-ref= : Referencia del procedimiento que verificó la titularidad (obligatoria para consolidar)}
        {--dry-run : Muestra los cambios sin aplicarlos}';

    protected $description = 'Resuelve un caso de consolidación de cuentas marcado para revisión';

    public function handle(ResolveConsolidationCaseHandler $resolveCase, AuditContext $auditContext): int
    {
        $decision = ConsolidationDecision::tryFrom((string) $this->option('decision'));

        if ($decision === null) {
            $this->error('--decision debe ser consolidate o separate.');

            return self::FAILURE;
        }

        $operator = trim((string) $this->option('operator'));

        if ($operator !== '') {
            // Resolutions and refusals alike are attributed to whoever was named (SPEC-014 V3).
            $auditContext->actAsConsoleOperator($operator);
        }

        try {
            $resolution = $resolveCase(new ResolveConsolidationCaseCommand(
                $operator,
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
