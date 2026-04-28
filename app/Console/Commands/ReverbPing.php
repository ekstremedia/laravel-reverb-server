<?php

namespace App\Console\Commands;

use App\Events\PingEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reverb:ping {message=pong}')]
#[Description('Broadcast a ping event on the public "ping" channel.')]
class ReverbPing extends Command
{
    public function handle(): int
    {
        $sentAt = now()->toIso8601String();
        $message = (string) $this->argument('message');

        broadcast(new PingEvent($sentAt, $message));

        $this->components->info("Ping broadcast on channel [ping] at {$sentAt}.");

        return self::SUCCESS;
    }
}
