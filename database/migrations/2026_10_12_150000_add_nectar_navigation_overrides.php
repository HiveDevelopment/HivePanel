<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nectar_extensions', fn (Blueprint $table) => $table->json('navigation_overrides')->nullable());
    }

    public function down(): void
    {
        Schema::table('nectar_extensions', fn (Blueprint $table) => $table->dropColumn('navigation_overrides'));
    }
};
