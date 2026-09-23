<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ut_regions: 39 daerah + 1 luar_negeri, keyed by official UN31.UT* codes.
        Schema::create('ut_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->enum('type', ['daerah', 'luar_negeri']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. programs: program studi
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. vendors registry
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('legal_name', 255);
            $table->string('contact_name', 255);
            $table->string('contact_email', 255);
            $table->enum('status', ['pending_verification', 'pending_approval', 'approved', 'rejected', 'suspended'])->default('pending_verification');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->unsignedInteger('scope_revision')->default(1);
            $table->timestamps();
        });

        // 4. vendor_ut_scope: approved UT scope per vendor
        Schema::create('vendor_ut_scope', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('ut_id')->constrained('ut_regions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'ut_id']);
        });

        // 5. Update users table with roles and scopes
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor', 'vendor'])->default('tutor')->after('password');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('role');
            $table->foreignId('ut_id')->nullable()->after('status')->constrained('ut_regions')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('ut_id')->constrained('vendors')->nullOnDelete();
            $table->unsignedInteger('permission_revision')->default(1)->after('vendor_id');
        });

        // 6. tutor_program: assignment prodi per tutor
        Schema::create('tutor_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'program_id']);
        });

        // 7. catalog_items
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_key', 100)->unique();
            $table->enum('item_type', ['package', 'book']);
            $table->string('item_code', 100);
            $table->string('edition', 50)->default('');
            $table->string('title', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['item_type', 'item_code', 'edition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('tutor_program');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ut_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['role', 'status', 'ut_id', 'vendor_id', 'permission_revision']);
        });
        Schema::dropIfExists('vendor_ut_scope');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('ut_regions');
    }
};
