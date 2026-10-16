<?php

namespace App\Jobs;

use App\Models\CellSubdomain;
use App\Services\Dns\CellSubdomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionCellSubdomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 240;

    public function __construct(public string $entryId) {}

    public function backoff(): array
    {
        return [15, 30, 60, 120];
    }

    public function handle(CellSubdomainService $service): void
    {
        $entry = CellSubdomain::find($this->entryId);
        if (! $entry || $entry->state === 'active') {
            return;
        }
        $entry->update(['state' => 'provisioning', 'error' => null]);
        $service->provision($entry);
    }
}
