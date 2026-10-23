<?php

namespace Tests\Unit;

use App\Services\Nectar\ExtensionManager;
use Tests\TestCase;

class NectarSdkManifestTest extends TestCase
{
    public function test_bundled_sdk_example_matches_the_installer_contract(): void
    {
        config(['nectar.panel_version' => 'dev']);
        $directory = base_path('extensions/hivepanel.hello-nectar');
        $manifest = json_decode(file_get_contents($directory.'/nectar.json'), true, 32, JSON_THROW_ON_ERROR);

        $this->assertSame($manifest, (new ExtensionManager)->validate($manifest));
        $this->assertFileExists($directory.'/'.$manifest['frontend']['entry']);
        foreach ($manifest['frontend']['pages'] as $page) {
            $this->assertFileExists($directory.'/'.$page['entry']);
        }
        $this->assertContains('cells.read', $manifest['permissions']);
        $this->assertSame('dashboard', $manifest['frontend']['pages'][0]['context']);
    }
}
