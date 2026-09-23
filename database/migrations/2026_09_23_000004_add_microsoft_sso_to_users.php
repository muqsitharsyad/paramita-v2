<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('microsoft_id')->nullable()->unique()->after('email_verified_at');
            $table->timestamp('last_sso_login_at')->nullable()->after('microsoft_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['microsoft_id']);
            $table->dropColumn(['microsoft_id', 'last_sso_login_at']);
        });
    }
};
