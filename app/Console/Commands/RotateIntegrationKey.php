<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Security\IntegrationKeyEncrypter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RotateIntegrationKey extends Command
{
    protected $signature = 'paramita:rotate-key';

    protected $description = 'Re-encrypt vendor credentials under new integration key per PRD §14.2';

    public function handle(IntegrationKeyEncrypter $encrypter): int
    {
        $revisions = DB::table('connection_revisions')->whereNotNull('auth_config_ciphertext')->get();
        $this->info('Found '.$revisions->count().' credentials to verify/re-encrypt.');

        foreach ($revisions as $rev) {
            try {
                $decrypted = $encrypter->decrypt($rev->auth_config_ciphertext);
                $newCipher = $encrypter->encrypt($decrypted);
                DB::table('connection_revisions')->where('id', $rev->id)->update([
                    'auth_config_ciphertext' => $newCipher,
                    'secret_version' => $rev->secret_version + 1,
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $this->error("Failed decrypting revision $rev->id: ".$e->getMessage());

                return 1;
            }
        }

        $this->info('Successfully rotated all credentials.');

        return 0;
    }
}
