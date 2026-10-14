<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MinecraftRegistryService
{
    public function request(string $method, string $path, array $data = []): Response
    {
        $client = Http::baseUrl(rtrim(config('hivepanel.registry_url'), '/').'/api/v1/minecraft')->acceptJson()->connectTimeout(10)->timeout(180);
        if ($token = config('hivepanel.registry_token')) {
            $client->withToken($token);
        }
        try {
            $response = $method === 'get' ? $client->get($path, $data) : $client->post($path, $data);
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages(['catalogue' => 'HiveRegistry is unavailable. Please try again shortly.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['catalogue' => $response->json('message') ?: 'HiveRegistry could not complete this request.']);
        }

        return $response;
    }

    public function json(string $method, string $path, array $data = []): array
    {
        return $this->request($method, $path, $data)->json();
    }
}
