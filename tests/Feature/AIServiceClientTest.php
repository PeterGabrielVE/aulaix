<?php

use AulaX\Ai\Application\AIServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The AI module (docker/ai-service) is a separate, decoupled service — these
 * tests never hit it over the network, they fake the HTTP boundary that
 * AulaX\Ai\Infrastructure\HttpAIServiceClient talks through.
 */
test('health returns the decoded payload when the AI service responds', function () {
    Http::fake([
        '*/health' => Http::response(['status' => 'ok', 'service' => 'aulaix-ai-service']),
    ]);

    $result = app(AIServiceClient::class)->health();

    expect($result)->toBe(['status' => 'ok', 'service' => 'aulaix-ai-service']);
});

test('health returns null when the AI service is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('connection refused'));

    $result = app(AIServiceClient::class)->health();

    expect($result)->toBeNull();
});
