<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Process status codes (01..07 in the PRD) become admin-managed reference data instead of a
 * hardcoded list in JS/config, so the code list vendors must use has ONE editable source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('label', 100);
            $table->string('bucket', 50)->nullable();   // grouping used by monitoring filters
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_statuses');
    }
};
