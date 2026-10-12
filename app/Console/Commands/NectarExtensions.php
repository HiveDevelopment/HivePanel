<?php
namespace App\Console\Commands;
use App\Services\Nectar\ExtensionManager;
use Illuminate\Console\Command;
use Throwable;
class NectarExtensions extends Command
{
    protected $signature = 'nectar:extensions {action : list|enable|disable|uninstall} {id?}';
    protected $description = 'Manage installed Nectar extensions';
    public function handle(ExtensionManager $manager): int
    {
        $action = $this->argument('action'); $id = $this->argument('id');
        if ($action === 'list') { $this->table(['ID','Name','Version','Enabled'], array_map(fn ($x) => [$x['id'],$x['name'],$x['version'],$x['enabled'] ? 'yes' : 'no'], $manager->installed())); return self::SUCCESS; }
        if (!$id || !in_array($action, ['enable','disable','uninstall'], true)) return self::FAILURE;
        try { if ($action === 'uninstall') $manager->uninstall($id); else $manager->setEnabled($id, $action === 'enable'); $this->info("{$action}: {$id}"); return self::SUCCESS; }
        catch (Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
