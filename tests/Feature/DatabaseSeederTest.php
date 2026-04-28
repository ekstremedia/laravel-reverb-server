<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the default admin when no env overrides are set', function () {
    (new DatabaseSeeder)->run();

    $user = User::where('email', 'admin@admin.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('wspassword', $user->password))->toBeTrue();
});

it('uses SEED_ADMIN_* env vars when provided', function () {
    $_ENV['SEED_ADMIN_EMAIL'] = 'owner@example.test';
    $_ENV['SEED_ADMIN_PASSWORD'] = 'super-secret-pass';
    $_ENV['SEED_ADMIN_NAME'] = 'Owner';

    try {
        (new DatabaseSeeder)->run();

        $user = User::where('email', 'owner@example.test')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Owner')
            ->and(Hash::check('super-secret-pass', $user->password))->toBeTrue();
    } finally {
        unset($_ENV['SEED_ADMIN_EMAIL'], $_ENV['SEED_ADMIN_PASSWORD'], $_ENV['SEED_ADMIN_NAME']);
    }
});

it('updates the existing admin when run again with new env values', function () {
    (new DatabaseSeeder)->run();

    $_ENV['SEED_ADMIN_PASSWORD'] = 'rotated-password';

    try {
        (new DatabaseSeeder)->run();

        $user = User::where('email', 'admin@admin.com')->first();

        expect(Hash::check('rotated-password', $user->password))->toBeTrue();
    } finally {
        unset($_ENV['SEED_ADMIN_PASSWORD']);
    }
});
