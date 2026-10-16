<?php

namespace App\Services\Dns;

use App\Models\Cell;
use App\Models\CellDomain;
use App\Models\CellSubdomain;
use App\Models\SubdomainZone;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Node\WebProxyNodeClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CellSubdomainService
{
    public function __construct(private ManagedDnsService $dns, private WebProxyNodeClient $proxy, private CellToolsPolicy $policy) {}

    public function reserve(Cell $cell, array $data): CellSubdomain
    {
        return DB::transaction(function () use ($cell, $data) {
            $current = Cell::whereKey($cell->id)->lockForUpdate()->firstOrFail();
            if (CellSubdomain::where('cell_id', $cell->id)->count() >= $this->policy->limit($cell, 'subdomain_limit')) {
                throw ValidationException::withMessages(['label' => __('Subdomain limit reached.')]);
            }$zone = SubdomainZone::whereKey($data['zone_id'])->where('enabled', true)->lockForUpdate()->firstOrFail();
            $label = strtolower(trim($data['label']));
            $reserved = array_merge(['www', 'mail', 'smtp', 'imap', 'admin', 'panel', 'api', 'ftp', 'ns1', 'ns2', 'autodiscover'], (array) $zone->reserved_labels);
            if (in_array($label, $reserved, true)) {
                throw ValidationException::withMessages(['label' => __('This label is reserved.')]);
            }$hostname = $label.'.'.$zone->domain;
            if (strlen($hostname) > 253 || CellDomain::where('domain', $hostname)->exists() || CellSubdomain::where('hostname', $hostname)->exists()) {
                throw ValidationException::withMessages(['label' => __('This hostname is unavailable.')]);
            }$allocation = $current->allocations()->whereKey($data['allocation_id'])->firstOrFail();
            $desired = data_get($current->metadata, 'desired_allocation_ids', $current->allocations()->pluck('id')->all());
            if (! in_array($allocation->id, $desired, true)) {
                throw ValidationException::withMessages(['allocation_id' => __('Choose an active Cell allocation.')]);
            }

            return CellSubdomain::create(['zone_id' => $zone->id, 'cell_id' => $cell->id, 'allocation_id' => $allocation->id, 'hostname' => $hostname, 'kind' => $data['kind'], 'records' => [], 'state' => 'pending']);
        });
    }

    public function provision(CellSubdomain $entry): void
    {
        Cache::lock('cell-subdomain-'.$entry->id, 300)->block(5, function () use ($entry) {
            $fresh = $entry->fresh();
            if ($fresh) {
                $this->provisionLocked($fresh);
            }
        });
    }

    private function provisionLocked(CellSubdomain $entry): void
    {
        $zone = SubdomainZone::findOrFail($entry->zone_id);
        $cell = Cell::with('node', 'allocation', 'allocations')->findOrFail($entry->cell_id);
        $allocation = $cell->allocations()->whereKey($entry->allocation_id)->firstOrFail();
        $records = $entry->records ?? [];
        try {
            $target = trim($cell->node->public_fqdn ?: $cell->node->fqdn);
            if (filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $record = ['type' => 'A', 'content' => $target, 'proxied' => false];
            } elseif (filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $record = ['type' => 'AAAA', 'content' => $target, 'proxied' => false];
            } else {
                $record = ['type' => 'CNAME', 'content' => $target, 'proxied' => false];
            }
            if ($entry->kind === 'web') {
                $settings = $this->proxy->request($cell->node, 'GET', '/configuration/web-proxy');
                if (! data_get($settings, 'config.enabled')) {
                    throw ValidationException::withMessages(['dns' => __('The node web proxy must be enabled before adding a website subdomain.')]);
                }
                $target = data_get($settings, 'config.dns_address');
                $record = ['type' => filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'A' : (filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'CNAME'), 'content' => $target, 'proxied' => false];
            }
            $records['address'] = $this->dns->create($zone, $entry->hostname, $record, $entry->id);
            $entry->update(['records' => $records]);
            if ($entry->kind === 'minecraft') {
                $records['srv'] = $this->dns->create($zone, '_minecraft._tcp.'.$entry->hostname, ['type' => 'SRV', 'data' => ['priority' => 0, 'weight' => 5, 'port' => (int) $allocation->port, 'target' => $entry->hostname]], $entry->id);
                $entry->update(['records' => $records]);
            }
            if ($entry->kind === 'web') {
                $route = $entry->route_id ? CellDomain::find($entry->route_id) : null;
                if (! $route) {
                    $route = CellDomain::create(['cell_id' => $cell->id, 'node_id' => $cell->node_id, 'allocation_id' => $allocation->id, 'domain' => $entry->hostname, 'protocol' => 'http', 'public_port' => 0, 'https' => true, 'redirect' => true, 'max_body_mb' => 32]);
                    $entry->update(['route_id' => $route->id]);
                }
                $response = $this->proxy->request($cell->node, 'POST', '/cells/'.$cell->daemon_id.'/domains', ['id' => $route->id, 'domain' => $route->domain, 'port' => (int) $allocation->port, 'protocol' => 'http', 'public_port' => 0, 'https' => true, 'redirect' => true, 'max_body_mb' => 32]);
                $token = data_get($response, 'route.verification_token');
                if (! $token) {
                    throw ValidationException::withMessages(['dns' => __('The Worker did not return a domain ownership token.')]);
                }
                $records['verification'] = $this->dns->create($zone, '_hivepanel.'.$entry->hostname, ['type' => 'TXT', 'content' => 'hivepanel-verification='.$token], $entry->id);
                $entry->update(['records' => $records]);
                $this->proxy->request($cell->node, 'POST', '/cells/'.$cell->daemon_id.'/domains/'.$route->id.'/verify');
            }
            $entry->update(['state' => 'active', 'error' => null]);
        } catch (\Throwable $e) {
            report($e);
            $entry->update(['records' => $records, 'state' => 'failed', 'error' => $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : 'DNS setup could not finish. Retry or ask an administrator to check the zone.']);
            throw $e;
        }
    }

    public function remove(CellSubdomain $entry): void
    {
        Cache::lock('cell-subdomain-'.$entry->id, 300)->block(5, function () use ($entry) {
            $fresh = $entry->fresh();
            if ($fresh) {
                $this->removeLocked($fresh);
            }
        });
    }

    private function removeLocked(CellSubdomain $entry): void
    {
        $zone = SubdomainZone::findOrFail($entry->zone_id);
        $cell = Cell::with('node')->findOrFail($entry->cell_id);
        if ($entry->route_id) {
            $route = CellDomain::find($entry->route_id);
            if ($route) {
                $this->proxy->request($cell->node, 'DELETE', '/cells/'.$cell->daemon_id.'/domains/'.$route->id);
                $route->delete();
            }$entry->update(['route_id' => null]);
        }
        $remaining = $entry->records ?? [];
        foreach ($remaining as $key => $record) {
            $this->dns->remove($zone, $record, $entry->id);
            unset($remaining[$key]);
            $entry->update(['records' => $remaining]);
        }$entry->delete();
    }
}
