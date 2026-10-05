<?php

namespace App\Contracts;

/**
 * Boundary between the Laravel monolith and the decoupled AI module
 * (docker/ai-service). No AI features exist yet — this contract only
 * proves out the wiring so a future feature calls this interface, never
 * an HTTP client directly, keeping the implementation swappable.
 */
interface AIServiceClient
{
    /**
     * @return array{status: string, service: string}|null Null when the AI service is unreachable.
     */
    public function health(): ?array;
}
