<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withToken(config('ai.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.openai.model'),
                'input' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(match (data_get($response->json(), 'error.code') ?: data_get($response->json(), 'error.status')) {
                'insufficient_quota', 'credit_balance_exhausted', 'RESOURCE_EXHAUSTED' => 'OpenAI has no remaining quota or credits.',
                default => match ($response->status()) {
                    401, 403 => 'OpenAI rejected the API credentials or permissions.',
                    429 => 'OpenAI rate limit or quota exceeded.',
                    400, 404 => 'OpenAI rejected the model or request.',
                    default => 'OpenAI provider unavailable (HTTP ' . $response->status() . ').',
                },
            });
        }

        return data_get($response->json(), 'output.0.content.0.text')
            ?? throw new RuntimeException('OpenAI returned no text.');
    }
}