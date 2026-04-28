<?php

use App\Events\PingEvent;
use Illuminate\Support\Facades\Event;

it('broadcasts a ping when the public api/ping endpoint is hit', function () {
    Event::fake([PingEvent::class]);

    $this->post(route('api.ping'))->assertNoContent();

    Event::assertDispatched(PingEvent::class);
});

it('exposes the api.ping route to guests', function () {
    Event::fake([PingEvent::class]);

    // No actingAs, no session; behaves like a fresh visitor with a CSRF token.
    $this->post(route('api.ping'))->assertNoContent();
});
