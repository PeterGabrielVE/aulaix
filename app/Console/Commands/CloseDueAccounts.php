<?php

namespace App\Console\Commands;

use AulaX\IdentityAccess\Application\Account\CloseDueAccountsHandler;
use Illuminate\Console\Command;

/**
 * SPEC-004 IDG-R08.10, R08.11: closes the accounts whose 30 days of grace
 * ended, or postpones them while something still blocks it. Scheduled daily
 * (routes/console.php).
 */
class CloseDueAccounts extends Command
{
    protected $signature = 'identity:close-due-accounts';

    protected $description = 'Cierra las cuentas cuyo plazo de eliminación venció, o aplaza las que siguen bloqueadas';

    public function handle(CloseDueAccountsHandler $closeDueAccounts): int
    {
        $result = $closeDueAccounts();

        $this->info("Cuentas cerradas: {$result['closed']}. Aplazadas: {$result['postponed']}.");

        return self::SUCCESS;
    }
}
