<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combs', function (Blueprint $table) {
            $table->string('source', 32)
                ->default('local')
                ->after('game')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('combs', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};