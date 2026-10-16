<?php

namespace App\Services\Cells;

use App\Models\AppSetting;
use App\Models\Cell;
use App\Models\Comb;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CellToolsPolicy
{
    public function settings(): array
    {
        return array_replace(['allowed_comb_ids' => [], 'split_limit' => 2, 'instance_limit' => 5, 'subdomain_limit' => 3, 'firewall_enabled' => true], Schema::hasTable('app_settings') ? (AppSetting::where('key', 'cell_tools')->value('value') ?? []) : []);
    }

    public function limit(Cell $cell, string $name): int
    {
        $defaults = $this->settings();

        return max(0, (int) data_get($cell->metadata, 'feature_limits.'.$name, $defaults[$name] ?? 0));
    }

    public function offered(Cell $cell)
    {
        $ids = $this->settings()['allowed_comb_ids'];
        $current = data_get($cell->metadata, 'comb_id');

        return Comb::query()->where(fn ($q) => $q->whereIn('id', $ids)->orWhere('id', $current))->orderBy('name')->get();
    }

    public function assertComb(Cell $cell, Comb $comb, ?User $user = null): void
    {
        if ($user?->hasAdminPermission('admin.cells.update')) {
            return;
        }
        if ((string) data_get($cell->metadata, 'comb_id') === (string) $comb->id) {
            return;
        }
        if (! in_array((string) $comb->id, array_map('strval', $this->settings()['allowed_comb_ids']), true)) {
            throw ValidationException::withMessages(['comb_id' => __('This Comb is not offered for user switching.')]);
        }
    }

    public function owner(Cell $cell, User $user): void
    {
        abort_unless((string) $cell->owner_id === (string) $user->id, 403);
    }
}
