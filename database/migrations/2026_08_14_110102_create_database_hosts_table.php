<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_hosts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('driver', 32)->default('mysql');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('username');
            $table->text('password');
            $table->string('public_host')->nullable();
            $table->unsignedSmallInteger('public_port')->nullable();
            $table->unsignedInteger('max_databases')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['host', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_hosts');
    }
};