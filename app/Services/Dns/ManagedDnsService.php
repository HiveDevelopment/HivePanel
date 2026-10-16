<?php

namespace App\Services\Dns;

use App\Models\SubdomainZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ManagedDnsService
{
    private function api(SubdomainZone $zone, string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::baseUrl('https://api.cloudflare.com/client/v4')->withToken($zone->api_token)->acceptJson()->connectTimeout(10)->timeout(25)->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['dns' => __('The DNS provider could not be reached.')]);
        }
        if ($response->status() === 404 && $method === 'GET' && str_contains($path, '/dns_records/')) {
            return [];
        }
        if (! $response->successful() || ! $response->json('success')) {
            throw ValidationException::withMessages(['dns' => __('The DNS provider rejected this operation. Check the zone ID, scoped token and existing records.')]);
        }

        return $response->json('result') ?? [];
    }

    public function verifyZone(SubdomainZone $zone): void
    {
        $remote = $this->api($zone, 'GET', '/zones/'.$zone->provider_zone_id);
        $base = strtolower(rtrim($remote['name'] ?? '', '.'));
        if ($base === '' || ($zone->domain !== $base && ! str_ends_with($zone->domain, '.'.$base))) {
            throw ValidationException::withMessages(['domain' => __('The configured domain does not belong to this DNS zone.')]);
        }
    }

    public function create(SubdomainZone $zone, string $name, array $record, string $reservation): array
    {
        // Never replace records belonging to another service, even when the contents match.
        $existing = $this->api($zone, 'GET', '/zones/'.$zone->provider_zone_id.'/dns_records', ['name' => $name, 'per_page' => 100]);
        foreach ($existing as $item) {
            if (($item['comment'] ?? '') === 'HivePanel '.$reservation && ($item['type'] ?? '') === $record['type']) {
                $this->api($zone, 'PATCH', '/zones/'.$zone->provider_zone_id.'/dns_records/'.$item['id'], [...$record, 'name' => $name, 'ttl' => 120, 'comment' => 'HivePanel '.$reservation]);

                return ['id' => $item['id'], 'name' => $name, 'type' => $record['type']];
            }
        }
        if (count($existing)) {
            throw ValidationException::withMessages(['hostname' => __('This hostname already has DNS records. Choose another subdomain.')]);
        }
        $result = $this->api($zone, 'POST', '/zones/'.$zone->provider_zone_id.'/dns_records', [...$record, 'name' => $name, 'ttl' => 120, 'comment' => 'HivePanel '.$reservation]);

        return ['id' => $result['id'], 'name' => $name, 'type' => $record['type']];
    }

    public function remove(SubdomainZone $zone, array $record, string $reservation): void
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $record['id'] ?? '')) {
            throw ValidationException::withMessages(['dns' => __('Invalid stored DNS record identifier.')]);
        }
        try {
            $remote = $this->api($zone, 'GET', '/zones/'.$zone->provider_zone_id.'/dns_records/'.$record['id']);
        } catch (ValidationException $e) {
            throw $e;
        }
        if (! $remote) {
            return;
        }
        if (($remote['comment'] ?? '') !== 'HivePanel '.$reservation || strtolower($remote['name'] ?? '') !== strtolower($record['name'] ?? '')) {
            throw ValidationException::withMessages(['dns' => __('DNS record ownership changed. An administrator must inspect it before removal.')]);
        }
        $this->api($zone, 'DELETE', '/zones/'.$zone->provider_zone_id.'/dns_records/'.$record['id']);
    }
}
