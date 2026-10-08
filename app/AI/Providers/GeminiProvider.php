<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $model = config('ai.gemini.model');
        $key = config('ai.gemini.key');

        $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userPrompt],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(match (data_get($response->json(), 'error.code') ?: data_get($response->json(), 'error.status')) {
                'insufficient_quota', 'credit_balance_exhausted', 'RESOURCE_EXHAUSTED' => 'Gemini has no remaining quota or credits.',
                default => match ($response->status()) {
                    401, 403 => 'Gemini rejected the API credentials or permissions.',
                    429 => 'Gemini rate limit or quota exceeded.',
                    400, 404 => 'Gemini rejected the model or request.',
                    default => 'Gemini provider unavailable (HTTP ' . $response->status() . ').',
                },
            });
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text')
            ?? throw new RuntimeException('Gemini returned no text.');
    }
}