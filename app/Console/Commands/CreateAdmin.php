<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'paramita:create-admin {--email=} {--name=}';

    protected $description = 'Create the initial Paramita administrator without storing a default password';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Administrator email'))));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Repeat password');

        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'same:confirmation'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email already exists.');

            return self::FAILURE;
        }

        Role::findOrCreate('admin', 'web');
        $admin = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'permission_revision' => 1,
        ]);
        $admin->assignRole('admin');

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
