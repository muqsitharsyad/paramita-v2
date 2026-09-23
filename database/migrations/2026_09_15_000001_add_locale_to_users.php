<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Remembered UI language per user; nullable = follow the app default.
            $table->string('locale', 5)->nullable()->after('permission_revision');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('locale');
            });
        }
    }
};
