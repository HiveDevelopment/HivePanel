<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nectar_extensions', function (Blueprint $table): void {
            $table->boolean('network_approved')->default(false);
        });
        Schema::create('nectar_network_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id', 160)->index();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('cell_id', 64)->nullable()->index();
            $table->string('operation', 40);
            $table->string('destination', 300)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nectar_network_audits');
        Schema::table('nectar_extensions', fn (Blueprint $table) => $table->dropColumn('network_approved'));
    }
};
