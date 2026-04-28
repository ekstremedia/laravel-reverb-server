<?php

use App\Events\PingEvent;
use App\Models\User;
use App\Models\WebsocketStat;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('redirects guests visiting the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders stats and the env block for an authenticated admin', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire('reverb-dashboard');
});

it('broadcasts a ping when the dashboard sendPing action is triggered', function () {
    Event::fake([PingEvent::class]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('reverb-dashboard')
        ->call('sendPing')
        ->assertSet('lastPingSentAt', fn ($value) => is_string($value) && $value !== '');

    Event::assertDispatched(PingEvent::class);
});

it('exposes the reverb config in the env block', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('reverb.apps.apps.0', [
        'app_id' => 'test-id',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'options' => [
            'host' => 'reverb.example',
            'port' => 8080,
            'scheme' => 'http',
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('reverb-dashboard')
        ->assertSee('REVERB_APP_ID=test-id')
        ->assertSee('REVERB_APP_KEY=test-key')
        ->assertSee('REVERB_HOST=reverb.example')
        ->assertSee('BROADCAST_CONNECTION=reverb');
});
