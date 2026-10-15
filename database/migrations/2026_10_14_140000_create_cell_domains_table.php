<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cell_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('node_id')->constrained()->cascadeOnDelete();
            $table->uuid('allocation_id');
            $table->string('domain', 253)->unique();
            $table->string('protocol', 4);
            $table->unsignedInteger('public_port')->default(0);
            $table->boolean('https')->default(false);
            $table->boolean('redirect')->default(false);
            $table->unsignedInteger('max_body_mb')->default(32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_domains');
    }
};
