<?php

namespace App\Services\Node;

use App\Models\Cell;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

class CellToolsNodeClient extends NodeClient
{
    public function request(Cell $cell, string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->client($cell->node)->withHeaders($headers)->timeout(300)->connectTimeout(10)->send($method, '/cells/'.rawurlencode($cell->daemon_id).$path, $method === 'GET' ? [] : ['json' => $data]);
        } catch (ConnectionException $e) {
            report($e);
            throw ValidationException::withMessages(['tools' => __('The node did not respond. Refresh the operation status before retrying.')]);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['tools' => $response->json('message') ?: 'The node could not complete this operation.']);
        }

        return $response->json() ?? [];
    }
}
