<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HivePasteService
{
    public function create(string $content, string $title, string $language = 'text'): array
    {
        if (! config('services.hivepaste.enabled', true)) {
            throw new RuntimeException('HivePaste sharing is disabled.');
        }

        if ($content === '' || strlen($content) > (int) config('services.hivepaste.max_bytes', 524288)) {
            throw ValidationException::withMessages(['content' => 'Content is empty or exceeds the HivePaste sharing limit.']);
        }

        if (str_contains($content, "\0") || ! mb_check_encoding($content, 'UTF-8')) {
            throw ValidationException::withMessages(['content' => 'Only UTF-8 text files can be shared.']);
        }

        $url = rtrim((string) config('services.hivepaste.url', 'https://paste.hivepanel.dev'), '/');
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('HivePaste requires an HTTPS URL.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post($url.'/api/v1/integrations/hivepanel/pastes', [
                'title' => mb_substr($title, 0, 120),
                'content' => $content,
                'language' => $language,
            ]);

        if ($response->status() === 429) {
            throw ValidationException::withMessages(['content' => 'HivePaste is receiving too many requests. Please try again later.']);
        }

        if ($response->status() === 413) {
            throw ValidationException::withMessages(['content' => 'This content is too large for HivePaste.']);
        }

        if (! $response->successful() || ! is_string($response->json('url'))) {
            throw new RuntimeException('HivePaste could not create the share link.');
        }

        $shareUrl = $response->json('url');
        if (! str_starts_with($shareUrl, $url.'/')) {
            throw new RuntimeException('HivePaste returned an unexpected share URL.');
        }

        return ['url' => $shareUrl, 'id' => $response->json('id')];
    }
}