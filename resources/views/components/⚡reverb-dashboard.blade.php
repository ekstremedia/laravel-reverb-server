<?php

use App\Events\PingEvent;
use App\Models\WebsocketStat;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $lastPingSentAt = null;

    public function sendPing(): void
    {
        $this->lastPingSentAt = now()->toIso8601String();

        broadcast(new PingEvent($this->lastPingSentAt));

        Flux::toast(variant: 'success', text: __('Ping broadcast on channel "ping".'));
    }

    public function refreshStats(): void
    {
        // wire:poll target — re-renders to show updated counters
    }

    #[Computed]
    public function totals(): array
    {
        return collect(WebsocketStat::METRICS)->mapWithKeys(fn (string $metric) => [
            $metric => [
                'today' => WebsocketStat::todayFor($metric),
                'total' => WebsocketStat::totalFor($metric),
            ],
        ])->all();
    }

    #[Computed]
    public function messagesSentSeries(): array
    {
        return WebsocketStat::lastDays(WebsocketStat::METRIC_MESSAGE_SENT, 7);
    }

    #[Computed]
    public function reverbReachable(): bool
    {
        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        $port = (int) config('reverb.servers.reverb.port', 8080);

        if ($host === '0.0.0.0') {
            $host = '127.0.0.1';
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    #[Computed]
    public function reverbConfig(): array
    {
        $reverbApp = config('reverb.apps.apps.0', []);

        return [
            'BROADCAST_CONNECTION' => config('broadcasting.default'),
            'REVERB_APP_ID' => $reverbApp['app_id'] ?? '',
            'REVERB_APP_KEY' => $reverbApp['key'] ?? '',
            'REVERB_APP_SECRET' => $reverbApp['secret'] ?? '',
            'REVERB_HOST' => data_get($reverbApp, 'options.host', ''),
            'REVERB_PORT' => (string) data_get($reverbApp, 'options.port', ''),
            'REVERB_SCHEME' => data_get($reverbApp, 'options.scheme', ''),
        ];
    }

    #[Computed]
    public function envBlock(): string
    {
        $config = $this->reverbConfig();

        return <<<ENV
BROADCAST_CONNECTION={$config['BROADCAST_CONNECTION']}

REVERB_APP_ID={$config['REVERB_APP_ID']}
REVERB_APP_KEY={$config['REVERB_APP_KEY']}
REVERB_APP_SECRET={$config['REVERB_APP_SECRET']}
REVERB_HOST={$config['REVERB_HOST']}
REVERB_PORT={$config['REVERB_PORT']}
REVERB_SCHEME={$config['REVERB_SCHEME']}

VITE_REVERB_APP_KEY="\${REVERB_APP_KEY}"
VITE_REVERB_HOST="\${REVERB_HOST}"
VITE_REVERB_PORT="\${REVERB_PORT}"
VITE_REVERB_SCHEME="\${REVERB_SCHEME}"
ENV;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4" wire:poll.5s="refreshStats">
    {{-- Header + status --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">{{ __('Reverb WebSocket Server') }}</flux:heading>

            @if ($this->reverbReachable)
                <flux:badge color="lime" icon="signal">{{ __('Online') }}</flux:badge>
            @else
                <flux:badge color="red" icon="exclamation-triangle">{{ __('Unreachable') }}</flux:badge>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="sendPing" variant="primary" icon="paper-airplane">
                {{ __('Send ping') }}
            </flux:button>
        </div>
    </div>

    {{-- Stats grid --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach (\App\Models\WebsocketStat::METRICS as $metric)
            <div class="flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ str(str_replace('_', ' ', $metric))->title() }}
                </flux:text>
                <flux:heading size="xl">{{ number_format($this->totals[$metric]['total']) }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Today') }}: {{ number_format($this->totals[$metric]['today']) }}
                </flux:text>
            </div>
        @endforeach
    </div>

    {{-- 7-day messages-sent chart + ping log --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="md" class="mb-3">{{ __('Messages sent (last 7 days)') }}</flux:heading>

            @php
                $series = $this->messagesSentSeries;
                $max = max(1, collect($series)->max('count'));
            @endphp

            <div class="flex h-40 items-end gap-2">
                @foreach ($series as $day)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                        <div
                            class="w-full rounded-t bg-emerald-500 dark:bg-emerald-600"
                            style="height: {{ max(2, (int) round(($day['count'] / $max) * 100)) }}%"
                            title="{{ $day['date'] }}: {{ $day['count'] }}"
                        ></div>
                        <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">
                            {{ \Illuminate\Support\Carbon::parse($day['date'])->format('D') }}
                        </flux:text>
                        <flux:text size="xs" class="font-mono">{{ $day['count'] }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900"
             x-data="{
                pings: [],
                lastSentAt: null,
                init() {
                    if (!window.Echo) return;
                    window.Echo.channel('ping').listen('.ping', (e) => {
                        const now = Date.now();
                        const sent = new Date(e.sent_at).getTime();
                        const rtt = now - sent;
                        this.pings.unshift({ at: new Date().toLocaleTimeString(), rtt, message: e.message });
                        if (this.pings.length > 8) this.pings.pop();
                    });
                }
             }">
            <flux:heading size="md" class="mb-3">{{ __('Ping / pong log') }}</flux:heading>

            <template x-if="pings.length === 0">
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Click "Send ping" to broadcast a test event. Received pings will appear here.') }}
                </flux:text>
            </template>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <template x-for="ping in pings" :key="ping.at + ping.rtt">
                    <li class="flex items-center justify-between gap-2 py-2 font-mono text-sm">
                        <span x-text="ping.at" class="text-zinc-500 dark:text-zinc-400"></span>
                        <span x-text="ping.message" class="flex-1 ms-3"></span>
                        <span x-text="ping.rtt + ' ms'" class="text-emerald-600 dark:text-emerald-400"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- .env block to copy into another Laravel app --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900"
         x-data="{
            copied: false,
            copy() {
                navigator.clipboard.writeText(this.$refs.envBlock.innerText);
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            }
         }">
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="md">{{ __('Connection .env') }}</flux:heading>
            <flux:button x-on:click="copy" variant="ghost" size="sm" icon="clipboard">
                <span x-show="!copied">{{ __('Copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
            </flux:button>
        </div>

        <flux:text size="sm" class="mb-2 text-zinc-500 dark:text-zinc-400">
            {{ __('Paste these into your other Laravel application\'s .env to broadcast through this server.') }}
        </flux:text>

        <pre x-ref="envBlock" class="overflow-x-auto rounded-lg bg-zinc-100 p-3 font-mono text-xs leading-5 dark:bg-zinc-800">{{ $this->envBlock }}</pre>
    </div>
</div>
