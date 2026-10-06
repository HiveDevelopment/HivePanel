<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (AdminPermissions::groups() as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name],
                    [
                        'id' => (string) Str::uuid(),
                        'label' => $label,
                        'group' => $group,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', AdminPermissions::all())->delete();
    }
};
