<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class NectarServiceProvider extends ServiceProvider {
 public function boot(): void {
  $this->loadRoutesFrom(base_path('routes/nectar.php'));
 }
}
