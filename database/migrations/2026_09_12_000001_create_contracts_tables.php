<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 100)->unique();
            $table->enum('module', ['stock', 'delivery']);
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('version', 50)->default('1.0.0');
            $table->json('schema_json');
            $table->json('request_schema_json')->nullable();
            $table->enum('status', ['draft', 'published', 'deprecated', 'retired'])->default('draft');
            $table->string('suite_version', 50)->default('1.0.0');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retire_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contracts');
    }
};
