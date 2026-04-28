<?php

namespace App\Console\Commands;

use App\Models\WebsocketStat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reverb:stats')]
#[Description('Display total and today counters for Reverb websocket activity.')]
class ReverbStats extends Command
{
    public function handle(): int
    {
        $rows = collect(WebsocketStat::METRICS)->map(fn (string $metric) => [
            'metric' => $metric,
            'today' => WebsocketStat::todayFor($metric),
            'total' => WebsocketStat::totalFor($metric),
        ])->all();

        $this->table(['Metric', 'Today', 'Total'], $rows);

        return self::SUCCESS;
    }
}
