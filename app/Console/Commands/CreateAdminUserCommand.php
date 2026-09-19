<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin {username : The admin username (NIP)} {--email= : Optional email address} {--password= : Password (prompted when omitted)}')]
#[Description('Create the initial administrator account')]
class CreateAdminUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $email = $this->option('email');
        $password = (string) ($this->option('password') ?? $this->secret('Password'));

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'username' => ['required', 'string', 'max:255', 'unique:'.User::class],
            'email' => ['nullable', 'email', 'max:255', 'unique:'.User::class],
            // No 'confirmed' rule — there is no confirmation prompt on the CLI.
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->info("Admin user [{$username}] created.");

        return self::SUCCESS;
    }
}
