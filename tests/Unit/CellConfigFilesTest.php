<?php

namespace Tests\Unit;

use App\Services\Cells\CellConfigFiles;
use PHPUnit\Framework\TestCase;

class CellConfigFilesTest extends TestCase
{
    public function test_other_games_do_not_inherit_minecraft_files(): void
    {
        $catalog = new CellConfigFiles();
        $this->assertSame([], $catalog->declared(['game' => 'rust']));
        $this->assertSame('server.properties', $catalog->declared(['game' => 'minecraft'])[0]['path']);
    }

    public function test_comb_can_declare_nested_configuration_without_unsafe_paths(): void
    {
        $files = (new CellConfigFiles())->declared(['config_files' => ['../secret', '/etc/passwd', 'bad\\path', ['path' => 'Game/Saved/Config/server.ini', 'title' => 'Game settings']]]);
        $this->assertCount(1, $files);
        $this->assertSame('Game settings', $files[0]['title']);
    }

    public function test_discovery_only_includes_configuration_files(): void
    {
        $files = (new CellConfigFiles())->discovered([['name' => 'config.json'], ['name' => 'package.json'], ['name' => 'folder.ini', 'is_dir' => true], ['name' => 'binary.exe'], ['name' => "bad\x00.json"]]);
        $this->assertSame(['config.json'], array_column($files, 'path'));
    }
}
