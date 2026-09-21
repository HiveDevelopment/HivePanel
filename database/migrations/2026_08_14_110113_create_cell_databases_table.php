<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cell_id');
            $table->uuid('database_host_id');
            $table->string('database_name', 64);
            $table->string('username', 32);
            $table->text('password');
            $table->string('allowed_host', 255)->default('%');
            $table->string('charset', 64)->default('utf8mb4');
            $table->string('collation', 64)->default('utf8mb4_unicode_ci');
            $table->boolean('managed')->default(true);
            $table->string('source', 32)->default('panel');
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('cell_id')
                ->references('id')
                ->on('cells')
                ->cascadeOnDelete();

            $table->foreign('database_host_id')
                ->references('id')
                ->on('database_hosts')
                ->restrictOnDelete();

            $table->unique(['database_host_id', 'database_name']);
            $table->unique(['database_host_id', 'username', 'allowed_host']);
            $table->index(['cell_id', 'created_at']);
            $table->index(['source', 'source_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_databases');
    }
};