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

    public function test_resource_recommendations_and_shell_are_preserved(): void
    {
        $recommendations=['memory_mb'=>128,'disk_mb'=>1024,'cpu_percent'=>25,'swap_mb'=>-1];
        $result=$this->synchronise(['recommended_resources'=>$recommendations,'shell'=>'sh']);
        $this->assertSame($recommendations,$result['recommended_resources']);
        $this->assertSame('sh',$result['shell']);
    }

    public function test_invalid_resource_recommendations_are_reported(): void
    {
        foreach ([['memory_mb'=>-1],['cpu_percent'=>1001],['io_weight'=>0],['unexpected'=>123]] as $recommendations) {
            try {$this->synchronise(['recommended_resources'=>$recommendations]);$this->fail('Invalid recommendation accepted.');}
            catch (ValidationException $exception) {$this->assertArrayHasKey('manifest',$exception->errors());}
        }
    }
}
