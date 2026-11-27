<?php

namespace App\Console\Commands;

use AulaX\Ai\Application\AIServiceClient;
use Illuminate\Console\Command;

class CheckAiServiceHealth extends Command
{
    protected $signature = 'ai:health';

    protected $description = 'Checks connectivity to the decoupled AI service through AulaX\Ai\Application\AIServiceClient';

    public function handle(AIServiceClient $client): int
    {
        $result = $client->health();

        if ($result === null) {
            $this->error('AI service unreachable.');

            return self::FAILURE;
        }

        $this->info("AI service reachable: {$result['status']} ({$result['service']})");

        return self::SUCCESS;
    }
}
