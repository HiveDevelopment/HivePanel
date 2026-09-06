<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_database_hosts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');
            $table->uuid('database_host_id');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_primary')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('database_host_id')->references('id')->on('database_hosts')->cascadeOnDelete();
            $table->unique(['node_id', 'database_host_id']);
            $table->index(['node_id', 'enabled', 'is_primary', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_database_hosts');
    }
};