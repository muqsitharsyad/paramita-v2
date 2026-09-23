<?php

declare(strict_types=1);

namespace App\Services\Security;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;

class IntegrationKeyEncrypter
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $rawKey = getenv('PARAMITA_INTEGRATION_KEY') ?: null;
        if (class_exists(Config::class) && class_exists(Container::class) && Container::getInstance()) {
            try {
                $rawKey = config('app.paramita_integration_key') ?: $rawKey;
            } catch (\Throwable) {
                // ignore in unbooted unit tests
            }
        }

        if (empty($rawKey)) {
            throw new Exception('PARAMITA_INTEGRATION_KEY is required; refusing an insecure fallback key');
        } else {
            if (str_starts_with($rawKey, 'base64:')) {
                $rawKey = base64_decode(substr($rawKey, 7));
            }
        }

        if (strlen($rawKey) !== 32) {
            throw new Exception('PARAMITA_INTEGRATION_KEY must be exactly 32 bytes for AES-256-GCM');
        }

        // Dedicated Laravel Encrypter instance explicitly using AES-256-GCM
        $this->encrypter = new Encrypter($rawKey, 'aes-256-gcm');
    }

    public function encrypt(string $plainText): string
    {
        return $this->encrypter->encryptString($plainText);
    }

    public function decrypt(string $cipherText): string
    {
        return $this->encrypter->decryptString($cipherText);
    }
}
