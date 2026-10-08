<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withToken(config('ai.openrouter.key'))
            ->timeout(30)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('ai.openrouter.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(match (data_get($response->json(), 'error.code') ?: data_get($response->json(), 'error.status')) {
                'insufficient_quota', 'credit_balance_exhausted', 'RESOURCE_EXHAUSTED' => 'OpenRouter has no remaining quota or credits.',
                default => match ($response->status()) {
                    401, 403 => 'OpenRouter rejected the API credentials or permissions.',
                    429 => 'OpenRouter rate limit or quota exceeded.',
                    400, 404 => 'OpenRouter rejected the model or request.',
                    default => 'OpenRouter provider unavailable (HTTP ' . $response->status() . ').',
                },
            });
        }

        return data_get($response->json(), 'choices.0.message.content')
            ?? throw new RuntimeException('OpenRouter returned no text.');
    }
}