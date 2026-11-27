<?php

namespace App\Console\Commands;

use AulaX\Audit\Application\AuditContext;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolution;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
use AulaX\Platform\Application\Access\AuthenticateConsoleOperatorHandler;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Illuminate\Console\Command;

/**
 * SPEC-004 IDG-R11.2 – R11.7; SPEC-017 RSP-R06: support resolves one case
 * marked for review. Requires an interactive terminal, a valid TOTP from the
 * named operator (never passed as an argument or option), a dry-run preview
 * and explicit confirmation before applying changes.
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

    public function handle(
        AuthenticateConsoleOperatorHandler $authenticate,
        ResolveConsolidationCaseHandler $resolveCase,
        AuditContext $auditContext,
    ): int {
        $decision = ConsolidationDecision::tryFrom((string) $this->option('decision'));

        if ($decision === null) {
            $this->error('--decision debe ser consolidate o separate.');

            return self::FAILURE;
        }

        $operator = trim((string) $this->option('operator'));

        try {
            if (! $this->input->isInteractive()) {
                $authenticate($operator, null);
            }

            $code = (string) $this->secret('Código TOTP del operador');
            $authenticate($operator, $code);
        } catch (BusinessRuleViolation $refused) {
            $this->error($refused->getMessage().' No se aplicó ningún cambio.');

            return self::FAILURE;
        }

        // Resolutions and refusals alike are attributed to whoever was authenticated (SPEC-014 V3).
        $auditContext->actAsConsoleOperator($operator);

        $accounts = array_map('intval', (array) $this->argument('accounts'));
        $reason = (string) $this->option('reason');
        $verificationRef = $this->option('verification-ref');

        try {
            $preview = $resolveCase(new ResolveConsolidationCaseCommand(
                $operator,
                $accounts,
                $decision,
                $reason,
                $verificationRef,
                dryRun: true,
            ));
        } catch (CaseResolutionRefused $refused) {
            $this->error($refused->getMessage().' No se aplicó ningún cambio.');

            return self::FAILURE;
        }

        $plan = $this->describePlan($preview);

        if ((bool) $this->option('dry-run')) {
            $this->line('Simulación: no se aplicó ningún cambio.');
            $this->line($plan);

            return self::SUCCESS;
        }

        $this->line('Vista previa de los cambios:');
        $this->line($plan);

        if (! $this->confirm('¿Confirmas aplicar estos cambios?', false)) {
            $this->line('Cancelado: no se aplicó ningún cambio.');

            return self::SUCCESS;
        }

        try {
            $resolution = $resolveCase(new ResolveConsolidationCaseCommand(
                $operator,
                $accounts,
                $decision,
                $reason,
                $verificationRef,
                dryRun: false,
            ));
        } catch (CaseResolutionRefused $refused) {
            $this->error($refused->getMessage().' No se aplicó ningún cambio.');

            return self::FAILURE;
        }

        $this->line('Hecho.');
        $this->line($this->describePlan($resolution));

        return self::SUCCESS;
    }

    private function describePlan(CaseResolution $resolution): string
    {
        $affected = implode(', ', array_map(fn (int $id) => "#{$id}", $resolution->affectedIds));

        return $resolution->decision === ConsolidationDecision::Consolidate
            ? "Consolidar en #{$resolution->survivorId}: se mueven sus planteles y roles y se borran {$affected}."
            : "Mantener separadas {$affected}: no se unificarán nunca.";
    }
}
