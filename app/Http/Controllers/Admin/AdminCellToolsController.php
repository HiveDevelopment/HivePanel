<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CellSubdomain;
use App\Models\Comb;
use App\Models\SubdomainZone;
use App\Services\Cells\CellToolsPolicy;
use App\Services\Dns\ManagedDnsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCellToolsController extends Controller
{
    public function index(CellToolsPolicy $policy)
    {
        return Inertia::render('Admin/CellTools/Index', ['settings' => $policy->settings(), 'combs' => Comb::orderBy('name')->get(['id', 'name']), 'zones' => SubdomainZone::orderBy('domain')->get()->makeHidden('api_token')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['allowed_comb_ids' => ['present', 'array'], 'allowed_comb_ids.*' => ['required', 'uuid', 'exists:combs,id'], 'split_limit' => ['required', 'integer', 'min:0', 'max:20'], 'instance_limit' => ['required', 'integer', 'min:1', 'max:20'], 'subdomain_limit' => ['required', 'integer', 'min:0', 'max:20'], 'firewall_enabled' => ['required', 'boolean']]);
        AppSetting::updateOrCreate(['key' => 'cell_tools'], ['value' => $data]);

        return back()->with('success', __('Cell feature settings saved.'));
    }

    public function zone(Request $request, ManagedDnsService $dns)
    {
        $data = $request->validate(['id' => ['nullable', 'uuid'], 'domain' => ['required', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'], 'provider_zone_id' => ['required', 'regex:/^[a-f0-9]{32}
$/'], 'api_token' => ['nullable', 'string', 'max:512'], 'enabled' => ['required', 'boolean'], 'reserved_labels' => ['present', 'array', 'max:100'], 'reserved_labels.*' => ['required', 'regex:/^[a-z0-9-]{1,63}
$/']]);
        $zone = empty($data['id']) ? new SubdomainZone : SubdomainZone::findOrFail($data['id']);
        if ($zone->exists && ($zone->domain !== $data['domain'] || $zone->provider_zone_id !== $data['provider_zone_id'])) {
            abort_if(CellSubdomain::where('zone_id', $zone->id)->exists(), 422, 'Remove generated subdomains before changing this zone.');
        }
        $token = $data['api_token'] ?? '';
        unset($data['id'],$data['api_token']);
        $zone->fill($data);
        if ($token !== '') {
            $zone->api_token = $token;
        }abort_if(! $zone->api_token, 422, 'A scoped DNS API token is required.');
        $dns->verifyZone($zone);
        $zone->save();

        return back()->with('success', __('Subdomain zone saved.'));
    }

    public function destroyZone(string $zone)
    {
        abort_if(CellSubdomain::where('zone_id', $zone)->exists(), 422, 'Remove generated subdomains before deleting this zone.');
        SubdomainZone::findOrFail($zone)->delete();

        return back()->with('success', __('Zone removed.'));
    }
}
