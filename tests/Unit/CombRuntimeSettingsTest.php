<?php

namespace Tests\Unit;

use App\Models\Cell;
use App\Models\Comb;
use App\Services\Cells\CellReinstallService;
use App\Services\Cells\CombImageService;
use App\Services\Node\CellNodeClient;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CombRuntimeSettingsTest extends TestCase
{
    public function test_only_offered_images_are_selectable_and_existing_choice_is_retained(): void
    {
        $manifest = ['image' => 'java:25', 'docker_images' => ['Java 25' => 'java:25', 'Java 21' => 'java:21', 'Java 8' => 'java:8']];
        $service = new CombImageService;
        $this->assertCount(3, $service->options($manifest));
        $this->assertSame('java:21', $service->choose($manifest, null, 'java:21'));
        $this->assertSame('java:8', $service->choose($manifest, 'java:8'));
        $this->expectException(ValidationException::class);
        $service->choose($manifest, 'unoffered:latest');
    }

    public function test_reinstall_keeps_existing_variables_and_accepts_edits(): void
    {
        $cell = new Cell(['comb' => 'example', 'metadata' => ['variables' => ['version' => 'existing', 'token' => 'private', 'unrelated' => 'discard']]]);
        $comb = new Comb(['external_id' => 'example', 'data' => ['variables_schema' => [['name' => 'version', 'default' => 'default', 'required' => true], ['name' => 'token', 'default' => '', 'secret' => true]]]]);
        $service = new CellReinstallService(Mockery::mock(CellNodeClient::class));
        $variables = $service->variablesForReinstall($cell, $comb, ['version' => 'edited']);
        $this->assertSame('edited', $variables['version']);
        $this->assertSame('private', $variables['token']);
        $this->assertArrayNotHasKey('unrelated', $variables);
        $this->assertSame('existing', $service->variablesForReinstall($cell, $comb, [])['version']);
        $comb->external_id = 'different';
        $this->assertSame('default', $service->variablesForReinstall($cell, $comb, [])['version']);
    }

    public function test_reinstall_cannot_override_internal_variables_or_clear_required_values(): void
    {
        $cell = new Cell(['comb' => 'example']);
        $comb = new Comb(['external_id' => 'example', 'data' => ['variables_schema' => [['name' => 'version', 'default' => '1', 'required' => true]]]]);
        $service = new CellReinstallService(Mockery::mock(CellNodeClient::class));
        $this->expectException(ValidationException::class);
        $service->variablesForReinstall($cell, $comb, ['server_port' => '1234']);
    }
}
