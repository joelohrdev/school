<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Single-user app holding minors' school records: only the first account may register.
        abort_unless(self::registrationOpen(), 403, 'Registration is closed.');

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Open only while no account exists, and in production only when
     * REGISTRATION_OPEN=true. See config/auth.php.
     */
    public static function registrationOpen(): bool
    {
        return (bool) config('auth.registration_open') && ! User::query()->exists();
    }
}
