<?php

namespace App\Console\Commands;

use AulaX\IdentityAccess\Application\Consolidation\CaseAccount;
use AulaX\IdentityAccess\Application\Consolidation\ListConsolidationCasesHandler;
use Illuminate\Console\Command;

/**
 * SPEC-004 IDG-R11.1: the doubtful consolidation cases support has to resolve.
 */
class ListConsolidationCases extends Command
{
    protected $signature = 'identity:consolidation:list';

    protected $description = 'Lista los casos de consolidación de cuentas marcados para revisión';

    public function handle(ListConsolidationCasesHandler $listCases): int
    {
        $cases = $listCases();

        if ($cases === []) {
            $this->info('No hay casos en revisión.');

            return self::SUCCESS;
        }

        foreach ($cases as $case) {
            $this->line($case->email);

            foreach ($case->accounts as $account) {
                $this->line('  '.self::describe($account));
            }
        }

        return self::SUCCESS;
    }

    private static function describe(CaseAccount $account): string
    {
        $institutions = $account->institutions === [] ? 'sin planteles' : implode(', ', $account->institutions);

        return "#{$account->id} {$account->name} ({$institutions})";
    }
}
