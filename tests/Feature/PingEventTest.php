<?php

use App\Events\PingEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;

it('broadcasts on the public ping channel with the expected payload', function () {
    Event::fake([PingEvent::class]);

    $sentAt = now()->toIso8601String();

    broadcast(new PingEvent($sentAt, 'hello'));

    Event::assertDispatched(PingEvent::class, function (PingEvent $event) use ($sentAt) {
        $channels = $event->broadcastOn();

        return $channels[0] instanceof Channel
            && $channels[0]->name === 'ping'
            && $event->broadcastAs() === 'ping'
            && $event->broadcastWith() === ['sent_at' => $sentAt, 'message' => 'hello'];
    });
});

it('uses pong as the default message', function () {
    $event = new PingEvent(now()->toIso8601String());

    expect($event->message)->toBe('pong');
});

it('runs the reverb:ping artisan command without errors', function () {
    Event::fake([PingEvent::class]);

    $this->artisan('reverb:ping', ['message' => 'cli'])
        ->assertSuccessful();

    Event::assertDispatched(PingEvent::class, fn (PingEvent $event) => $event->message === 'cli');
});
