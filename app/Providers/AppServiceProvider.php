<?php

namespace App\Providers;

use App\Models\WebsocketStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\ConnectionPruned;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->recordReverbStats();
        $this->authorizeLogViewer();
    }

    protected function authorizeLogViewer(): void
    {
        // opcodes/log-viewer aborts in production unless a viewLogViewer
        // gate (or auth callback) is registered. Any authenticated user
        // can see logs — the dashboard is already login-gated.
        Gate::define('viewLogViewer', fn ($user) => $user !== null);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function recordReverbStats(): void
    {
        Event::listen(MessageSent::class, fn () => WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT));
        Event::listen(MessageReceived::class, fn () => WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_RECEIVED));
        Event::listen(ChannelCreated::class, fn () => WebsocketStat::record(WebsocketStat::METRIC_CHANNEL_CREATED));
        Event::listen(ChannelRemoved::class, fn () => WebsocketStat::record(WebsocketStat::METRIC_CHANNEL_REMOVED));
        Event::listen(ConnectionPruned::class, fn () => WebsocketStat::record(WebsocketStat::METRIC_CONNECTION_PRUNED));
    }
}
