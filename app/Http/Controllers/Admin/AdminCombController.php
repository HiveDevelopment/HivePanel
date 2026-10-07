<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comb;
use App\Services\RegistryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminCombController extends Controller
{
    private const CATEGORIES = [
        'game',
        'web',
        'database',
        'application',
        'bot',
        'voice',
        'runtime',
    ];

    public function index(RegistryService $registry)
    {
        return Inertia::render('Admin/Combs/Index', [
            'combs' => Comb::query()
                ->latest()
                ->get()
                ->map(fn (Comb $comb) => $this->combPayload($comb))
                ->values(),

            'registryCombs' => $registry->getCombs() ?? [],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Combs/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'external_id' => [
                'required',
                'string',
                'max:255',
                'unique:combs,external_id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'game' => ['nullable', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                Rule::in(self::CATEGORIES),
            ],
            'group' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/'],
            'manifest' => ['required', 'string'],
        ]);

        $json = json_decode($data['manifest'], true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            ! is_array($json)
        ) {
            return back()
                ->withErrors([
                    'manifest' => 'The Comb JSON is invalid.',
                ])
                ->withInput();
        }

        $json = $this->synchroniseManifest($json, $data);

        Comb::create([
            'external_id' => $data['external_id'],
            'name' => $data['name'],
            'game' => $data['game'] ?: ($json['game'] ?? 'unknown'),
            'source' => 'manual',
            'data' => $json,
        ]);

        return redirect()
            ->route('admin.combs.index')
            ->with('success', "Created {$data['name']}.");
    }

    public function show(Comb $comb)
    {
        return Inertia::render('Admin/Combs/Show', [
            'comb' => $this->combPayload($comb),
        ]);
    }

    public function edit(Comb $comb)
    {
        return Inertia::render('Admin/Combs/Edit', [
            'comb' => $this->combPayload($comb),
        ]);
    }

    public function update(Request $request, Comb $comb)
    {
        $data = $request->validate([
            'external_id' => [
                'required',
                'string',
                'max:255',
                'unique:combs,external_id,' . $comb->id,
            ],
            'name' => ['required', 'string', 'max:255'],
            'game' => ['nullable', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                Rule::in(self::CATEGORIES),
            ],
            'group' => ['required', 'string', 'max:255'],
            'manifest' => ['required', 'string'],
        ]);

        $json = json_decode($data['manifest'], true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            ! is_array($json)
        ) {
            return back()
                ->withErrors([
                    'manifest' => 'The Comb JSON is invalid.',
                ])
                ->withInput();
        }

        $json = $this->synchroniseManifest($json, $data);

        $source = match ($comb->source) {
            'registry' => 'registry_modified',
            'registry_modified' => 'registry_modified',
            'manual' => 'manual',
            default => 'manual',
        };

        $comb->update([
            'external_id' => $data['external_id'],
            'name' => $data['name'],
            'game' => $data['game'] ?: ($json['game'] ?? 'unknown'),
            'source' => $source,
            'data' => $json,
        ]);

        return redirect()
            ->route('admin.combs.show', $comb)
            ->with('success', "Updated {$data['name']}.");
    }

    public function importFromRegistry(
        string $id,
        RegistryService $registry
    ) {
        $remoteComb = $registry->getComb($id);

        abort_if(
            ! $remoteComb || ! isset($remoteComb['id']),
            404,
            'Comb not found in registry.'
        );

        $existing = Comb::query()
            ->where('external_id', $remoteComb['id'])
            ->first();

        if (
            $existing &&
            in_array(
                $existing->source,
                ['registry_modified', 'manual'],
                true
            )
        ) {
            return back()->withErrors([
                'comb' => 'This Comb has local changes. Delete it or reset it before importing the Registry version.',
            ]);
        }

        $comb = Comb::updateOrCreate(
            [
                'external_id' => $remoteComb['id'],
            ],
            [
                'name' => $remoteComb['name'] ?? $remoteComb['id'],
                'game' => $remoteComb['game'] ?? 'unknown',
                'source' => 'registry',
                'data' => $remoteComb,
            ]
        );

        return redirect()
            ->route('admin.combs.index')
            ->with(
                'success',
                "Imported {$comb->name} from HiveRegistry."
            );
    }

    public function destroy(Comb $comb)
    {
        $name = $comb->name;

        $comb->delete();

        return redirect()
            ->route('admin.combs.index')
            ->with('success', "Deleted {$name}.");
    }

    private function synchroniseManifest(
        array $manifest,
        array $data
    ): array {
        $category = strtolower(trim($data['category']));
        $group = strtolower(trim($data['group']));
        $game = isset($data['game'])
            ? strtolower(trim($data['game']))
            : '';

        $manifest['id'] = trim($data['external_id']);
        $manifest['name'] = trim($data['name']);
        $manifest['category'] = $category;
        $manifest['group'] = $group;

        if ($game !== '') {
            $manifest['game'] = $game;
        } else {
            unset($manifest['game']);
        }

        return $manifest;
    }

    private function combPayload(Comb $comb): array
    {
        return [
            'id' => $comb->id,
            'external_id' => $comb->external_id,
            'name' => $comb->name,
            'game' => $comb->game,
            'category' => $comb->category(),
            'group' => $comb->group(),
            'tags' => $comb->tags(),
            'capabilities' => $comb->capabilities(),
            'source' => $comb->source ?? 'manual',
            'data' => $comb->data,
            'created_at' => $comb->created_at?->toISOString(),
            'updated_at' => $comb->updated_at?->toISOString(),
        ];
    }
}