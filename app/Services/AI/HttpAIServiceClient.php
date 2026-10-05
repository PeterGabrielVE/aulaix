<?php

namespace App\Services\AI;

use App\Contracts\AIServiceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpAIServiceClient implements AIServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function health(): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)->timeout($this->timeout)->get('/health');

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('AI service health check failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
