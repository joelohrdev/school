<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates the one account, already verified', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Pat Parent')
        ->expectsQuestion('Email', 'pat@example.test')
        ->expectsQuestion('Password', 'a-long-test-password')
        ->expectsQuestion('Password again', 'a-long-test-password')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('pat@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-test-password', $user->password))->toBeTrue();
});

it('refuses when an account already exists', function () {
    User::factory()->create();

    $this->artisan('user:create')->assertFailed();

    expect(User::count())->toBe(1);
});

it('rejects invalid input without creating anything', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Pat Parent')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsQuestion('Password', 'one-password')
        ->expectsQuestion('Password again', 'another-password')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('keeps registration closed in production unless explicitly opened', function () {
    $config = fn (string $env, ?string $open) => (function () use ($env, $open) {
        $_ENV['APP_ENV'] = $env;
        $open === null ? putenv('REGISTRATION_OPEN') : putenv("REGISTRATION_OPEN={$open}");
        unset($_ENV['REGISTRATION_OPEN'], $_SERVER['REGISTRATION_OPEN']);
        if ($open !== null) {
            $_ENV['REGISTRATION_OPEN'] = $open;
        }

        return (require config_path('auth.php'))['registration_open'];
    })();

    try {
        expect($config('production', null))->toBeFalse()
            ->and($config('production', 'true'))->toBeTrue()
            ->and($config('local', null))->toBeTrue()
            ->and($config('local', 'false'))->toBeFalse();
    } finally {
        $_ENV['APP_ENV'] = 'testing';
        putenv('REGISTRATION_OPEN');
        unset($_ENV['REGISTRATION_OPEN']);
    }
});
