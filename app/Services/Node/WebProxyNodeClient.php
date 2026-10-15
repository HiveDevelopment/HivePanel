<?php

namespace App\Services\Node;

use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

class WebProxyNodeClient extends NodeClient
{
    public function request(Node $node, string $method, string $path, array $data = [], bool $deployment = false): array
    {
        try {
            $response = $this->client($node)->connectTimeout(10)->timeout($deployment ? 300 : 45)->send($method, $path, strtoupper($method) === 'GET' ? [] : ['json' => $data]);
        } catch (ConnectionException $e) {
            report($e);
            throw ValidationException::withMessages(['domains' => 'The node could not be reached. Check its connection and try again.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['domains' => $response->json('message') ?: 'The Worker could not apply this change. Check that it supports node proxies.']);
        }

        return $response->json() ?? [];
    }
}
