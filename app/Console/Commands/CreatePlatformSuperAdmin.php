<?php

namespace App\Console\Commands;

use AulaX\Audit\Application\AuditContext;
use AulaX\Platform\Application\Operators\InviteOperatorHandler;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Illuminate\Console\Command;

/**
 * SPEC-014 PLT-R02 (V3, step 1): the first SuperAdmin, created from the
 * server. It receives a link to choose its password and enrol its TOTP;
 * the platform log keeps the server user who ran this.
 */
class CreatePlatformSuperAdmin extends Command
{
    protected $signature = 'platform:create-super-admin
        {name : Nombre del Superadministrador}
        {email : Su correo, al que llega el enlace de acceso}';

    protected $description = 'Crea un Superadministrador de la plataforma y le envía su enlace de acceso';

    public function handle(InviteOperatorHandler $invite, AuditContext $auditContext): int
    {
        $auditContext->actAsConsoleOperator('consola: '.(get_current_user() ?: 'desconocido'));

        try {
            $invite((string) $this->argument('name'), (string) $this->argument('email'), OperatorRole::SuperAdmin, null);
        } catch (BusinessRuleViolation $violation) {
            $this->error($violation->getMessage());

            return self::FAILURE;
        }

        $this->info("Superadministrador creado. Se envió el enlace de acceso a {$this->argument('email')}; vence en 48 horas.");

        return self::SUCCESS;
    }
}
