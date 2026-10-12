<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('nectar_extensions', function (Blueprint $table) {
  $table->id(); $table->string('extension_id', 160)->unique(); $table->string('name');
  $table->string('version', 60); $table->boolean('enabled')->default(false);
  $table->json('manifest'); $table->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('nectar_extensions'); }
};
