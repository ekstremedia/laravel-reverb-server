<?php

use App\Models\User;

test('log viewer rejects guests', function () {
    $this->get('/settings/logs')->assertRedirect(route('login'));
});

test('log viewer is reachable for authenticated users in production', function () {
    config()->set('log-viewer.require_auth_in_production', true);
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get('/settings/logs')
        ->assertOk();
});
