<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Create the app's one account from the server, already verified, so
 * registration never has to be open on a public URL.
 */
class CreateUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'user:create';

    protected $description = 'Create the account for this app (only when none exists)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('An account already exists. This app has one account; change it under Settings.');

            return self::FAILURE;
        }

        $input = [
            'name' => (string) $this->ask('Name'),
            'email' => (string) $this->ask('Email'),
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Password again'),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Created {$user->email}. Sign in, then turn on two-factor sign-in under Settings, Security.");

        return self::SUCCESS;
    }
}
