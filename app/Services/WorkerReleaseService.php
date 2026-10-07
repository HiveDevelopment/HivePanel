<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WorkerReleaseService
{
    private const CACHE_KEY = 'hivepanel.worker_latest_version';

    public function latestVersion(): ?string
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            function (): ?string {
                try {
                    $response = Http::acceptJson()
                        ->withHeaders([
                            'User-Agent' => 'HivePanel',
                        ])
                        ->timeout(8)
                        ->get(
                            'https://api.github.com/repos/HiveDevelopment/HiveWorker/releases/latest'
                        );

                    if (! $response->successful()) {
                        return null;
                    }

                    $version = $response->json('tag_name');

                    if (! is_string($version) || trim($version) === '') {
                        return null;
                    }

                    return $this->normaliseVersion($version);
                } catch (Throwable) {
                    return null;
                }
            }
        );
    }

    public function normaliseVersion(?string $version): ?string
    {
        if (! is_string($version)) {
            return null;
        }

        $version = trim($version);

        if ($version === '') {
            return null;
        }

        return ltrim($version, "vV");
    }

    public function displayVersion(?string $version): ?string
    {
        $version = $this->normaliseVersion($version);

        return $version === null
            ? null
            : 'v'.$version;
    }

    public function isOutdated(
        ?string $currentVersion,
        ?string $latestVersion = null,
    ): bool {
        $current = $this->normaliseVersion($currentVersion);
        $latest = $this->normaliseVersion(
            $latestVersion ?? $this->latestVersion()
        );

        if ($current === null || $latest === null) {
            return false;
        }

        return version_compare($current, $latest, '<');
    }
}