<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE connection_revisions MODIFY auth_type ENUM('none', 'api_key_header', 'bearer', 'basic', 'oauth2_client_credentials', 'api_key_query', 'token_login') NOT NULL");
    }

    public function down(): void
    {
        DB::table('connection_revisions')->where('auth_type', 'token_login')->update(['auth_type' => 'none', 'auth_config_ciphertext' => null]);
        DB::statement("ALTER TABLE connection_revisions MODIFY auth_type ENUM('none', 'api_key_header', 'bearer', 'basic', 'oauth2_client_credentials', 'api_key_query') NOT NULL");
    }
};
