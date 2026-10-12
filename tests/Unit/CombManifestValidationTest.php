<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminCombController;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class CombManifestValidationTest extends TestCase
{
    private function synchronise(array $manifest): array
    {
        $method = new ReflectionMethod(AdminCombController::class, 'synchroniseManifest');

        return $method->invoke(new AdminCombController(), $manifest, [
            'external_id' => 'dragonwilds',
            'name' => 'RuneScape Dragonwilds',
            'category' => 'game',
            'group' => 'steam',
            'game' => 'dragonwilds',
        ]);
    }

    public function test_custom_commands_and_signal_only_shutdown_are_preserved(): void
    {
        $result = $this->synchronise([
            'console_commands' => ['listplayers', 'say '],
            'stop' => ['command' => '', 'timeout' => 90, 'signal' => 'SIGINT'],
            'image' => 'custom/image',
        ]);

        $this->assertSame(['listplayers', 'say '], $result['console_commands']);
        $this->assertSame('', $result['stop']['command']);
        $this->assertSame(90, $result['stop']['timeout']);
        $this->assertSame('custom/image', $result['image']);
        $this->assertSame('steam', $result['group']);
    }

    public function test_invalid_shutdown_settings_are_reported_as_manifest_errors(): void
    {
        foreach ([
            ['stop' => ['command' => "quit\nother-command"]],
            ['stop' => ['timeout' => 601]],
            ['stop' => ['signal' => 'SIGKILL']],
            ['console_commands' => ["listplayers\nquit"]],
        ] as $manifest) {
            try {
                $this->synchronise($manifest);
                $this->fail('Invalid Comb settings were accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('manifest', $exception->errors());
            }
        }
    }
}
