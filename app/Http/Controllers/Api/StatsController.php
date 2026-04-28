<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsocketStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $totals = collect(WebsocketStat::METRICS)->mapWithKeys(fn (string $metric) => [
            $metric => [
                'today' => WebsocketStat::todayFor($metric),
                'total' => WebsocketStat::totalFor($metric),
            ],
        ])->all();

        $last7 = collect(WebsocketStat::METRICS)->mapWithKeys(fn (string $metric) => [
            $metric => WebsocketStat::lastDays($metric, 7),
        ])->all();

        return response()->json([
            'status' => $this->reverbStatus(),
            'checked_at' => now()->toIso8601String(),
            'reverb' => [
                'host' => data_get(config('reverb.apps.apps.0'), 'options.host'),
                'port' => (int) data_get(config('reverb.apps.apps.0'), 'options.port', 0),
                'scheme' => data_get(config('reverb.apps.apps.0'), 'options.scheme'),
            ],
            'totals' => $totals,
            'last_7_days' => $last7,
        ]);
    }

    protected function reverbStatus(): string
    {
        $host = config('broadcasting.connections.reverb.options.host')
            ?: config('reverb.servers.reverb.host', '127.0.0.1');
        $port = (int) (config('broadcasting.connections.reverb.options.port')
            ?: config('reverb.servers.reverb.port', 8080));

        if ($host === '0.0.0.0' || $host === '') {
            $host = '127.0.0.1';
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

        if ($socket === false) {
            return 'offline';
        }

        fclose($socket);

        return 'online';
    }
}
