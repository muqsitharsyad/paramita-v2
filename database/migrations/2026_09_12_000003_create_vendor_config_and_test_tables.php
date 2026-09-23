<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. connections
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('label', 255)->default('API Utama');
            $table->timestamps();
        });

        // 2. connection_revisions: immutable snapshot base_url/auth/ciphertext
        Schema::create('connection_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('base_url', 2048);
            $table->enum('auth_type', ['none', 'api_key_header', 'bearer', 'basic', 'oauth2_client_credentials', 'api_key_query']);
            $table->text('auth_config_ciphertext')->nullable(); // Encrypted JSON TEXT
            $table->unsignedInteger('secret_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. endpoint_bindings: relationship vendor + contract slot
        Schema::create('endpoint_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedBigInteger('active_revision_id')->nullable();
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'contract_id']);
        });

        // 4. binding_revisions: immutable after test start
        Schema::create('binding_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binding_id')->constrained('endpoint_bindings')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('connection_revision_id')->constrained('connection_revisions')->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained('contract_versions')->cascadeOnDelete();
            $table->string('path', 2048);
            $table->json('static_query_json')->nullable();
            $table->enum('dispatch_mode', ['separate_path', 'single_url_dispatch'])->default('separate_path');
            $table->unsignedInteger('scope_revision')->default(1);
            $table->enum('status', ['draft', 'testing', 'test_passed', 'test_failed', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
        });

        // 5. endpoint_test_runs
        Schema::create('endpoint_test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binding_revision_id')->constrained('binding_revisions')->cascadeOnDelete();
            $table->foreignId('connection_revision_id')->constrained('connection_revisions')->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained('contract_versions')->cascadeOnDelete();
            $table->string('suite_version', 50)->default('1.0.0');
            $table->enum('status', ['queued', 'running', 'passed', 'failed', 'incomplete', 'timed_out', 'cancelled'])->default('queued');
            $table->json('report_json')->nullable(); // Sanitized, no raw PII / bodies
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['binding_revision_id', 'finished_at']);
        });

        // 6. submissions
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binding_revision_id')->constrained('binding_revisions')->cascadeOnDelete();
            $table->foreignId('test_run_id')->constrained('endpoint_test_runs')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('endpoint_test_runs');
        Schema::dropIfExists('binding_revisions');
        Schema::dropIfExists('endpoint_bindings');
        Schema::dropIfExists('connection_revisions');
        Schema::dropIfExists('connections');
    }
};
