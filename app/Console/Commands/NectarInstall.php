<?php
namespace App\Console\Commands;
use App\Services\Nectar\ExtensionManager;
use Illuminate\Console\Command;
use Throwable;
class NectarInstall extends Command
{
    protected $signature = 'nectar:install {file : Local .nectar archive path}';
    protected $description = 'Install a local Nectar extension (disabled by default)';
    public function handle(ExtensionManager $manager): int
    {
        try { $m = $manager->install($this->argument('file')); $this->info('Installed '.$m['id'].' v'.$m['version'].' (disabled)'); return self::SUCCESS; }
        catch (Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
