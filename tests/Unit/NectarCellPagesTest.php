<?php

namespace Tests\Unit;

use App\Models\Cell;
use App\Services\Nectar\ExtensionManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NectarCellPagesTest extends TestCase
{
    private function manifest(): array
    {
        return ['id' => 'test.native', 'name' => 'Test', 'version' => '0.9.1', 'panelCompatibility' => 'dev', 'frontend' => ['entry' => 'dist/index.html', 'combs' => ['game:minecraft'], 'pages' => [['slug' => 'test', 'title' => 'Vote Tester', 'entry' => 'dist/pages/test.html', 'context' => 'cell', 'sidebar' => ['group' => 'tools', 'icon' => 'vote']]]]];
    }

    public function test_cell_manifest_and_sidebar_placement_are_valid(): void
    {
        config(['nectar.panel_version' => 'dev']);
        $this->assertSame($this->manifest(), (new ExtensionManager())->validate($this->manifest()));
    }

    public function test_untrusted_sidebar_destination_is_rejected(): void
    {
        $manifest = $this->manifest(); $manifest['frontend']['pages'][0]['sidebar']['group'] = 'admin';
        $this->expectException(ValidationException::class);
        (new ExtensionManager())->validate($manifest);
    }

    public function test_cell_pages_are_hidden_without_matching_cell_and_honor_admin_placement(): void
    {
        Schema::create('nectar_extensions', function (Blueprint $table): void {
            $table->string('extension_id'); $table->string('name'); $table->string('version'); $table->boolean('enabled'); $table->boolean('network_approved'); $table->text('manifest'); $table->text('navigation_overrides')->nullable();
        });
        Schema::create('combs', function (Blueprint $table): void { $table->string('id'); });
        DB::table('nectar_extensions')->insert(['extension_id' => 'test.native', 'name' => 'Test', 'version' => '0.9.1', 'enabled' => true, 'network_approved' => false, 'manifest' => json_encode($this->manifest()), 'navigation_overrides' => '{"test":{"group":"configuration"}}']);
        $manager = new ExtensionManager();
        $this->assertSame([], $manager->pages());
        $cell = new Cell(['comb' => 'custom', 'metadata' => ['comb_data' => ['game' => 'minecraft']]]); $cell->id = 'cell';
        $pages = $manager->pages($cell);
        $this->assertCount(1, $pages);
        $this->assertSame('configuration', $pages[0]['sidebarGroup']);
        $this->assertStringContainsString('/cells/cell/extensions/test.native/test', $pages[0]['href']);
        $cell->metadata = ['comb_data' => ['game' => 'rust']];
        $this->assertSame([], $manager->pages($cell));
    }
}
