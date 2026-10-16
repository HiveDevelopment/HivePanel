<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PreinstallRegistryService
{
    public function json(string $method, string $path, array $data = []): array
    {
        $client = Http::baseUrl(rtrim(config('hivepanel.registry_url'), '/').'/api/v1/preinstalls')->acceptJson()->connectTimeout(10)->timeout(180);
        if ($token = config('hivepanel.registry_token')) {
            $client->withToken($token);
        }
        try {
            $response = $method === 'get' ? $client->get($path, $data) : $client->post($path, $data);
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages(['preinstall' => __('HiveRegistry is unavailable. Please try again shortly.')]);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['preinstall' => $response->json('message') ?: 'HiveRegistry could not complete this request.']);
        }

        return $response->json();
    }
}
