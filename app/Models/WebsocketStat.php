<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WebsocketStat extends Model
{
    public const METRIC_MESSAGE_SENT = 'message_sent';

    public const METRIC_MESSAGE_RECEIVED = 'message_received';

    public const METRIC_CHANNEL_CREATED = 'channel_created';

    public const METRIC_CHANNEL_REMOVED = 'channel_removed';

    public const METRIC_CONNECTION_PRUNED = 'connection_pruned';

    public const METRICS = [
        self::METRIC_MESSAGE_SENT,
        self::METRIC_MESSAGE_RECEIVED,
        self::METRIC_CHANNEL_CREATED,
        self::METRIC_CHANNEL_REMOVED,
        self::METRIC_CONNECTION_PRUNED,
    ];

    protected $fillable = ['metric', 'date', 'count'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'count' => 'integer',
    ];

    public static function record(string $metric, int $by = 1): void
    {
        try {
            $today = now()->toDateString();

            $affected = DB::table('websocket_stats')
                ->where('metric', $metric)
                ->where('date', $today)
                ->update([
                    'count' => DB::raw('count + '.$by),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                DB::table('websocket_stats')->insertOrIgnore([
                    'metric' => $metric,
                    'date' => $today,
                    'count' => $by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Stats table may not exist yet (e.g. before migrations) or DB may be locked.
            // Counters are best-effort and must never disrupt websocket traffic.
        }
    }

    public static function totalFor(string $metric): int
    {
        return (int) static::query()->where('metric', $metric)->sum('count');
    }

    public static function todayFor(string $metric): int
    {
        return (int) DB::table('websocket_stats')
            ->where('metric', $metric)
            ->where('date', now()->toDateString())
            ->value('count');
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    public static function lastDays(string $metric, int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('websocket_stats')
            ->where('metric', $metric)
            ->where('date', '>=', $start->toDateString())
            ->get(['date', 'count'])
            ->mapWithKeys(fn ($row) => [substr((string) $row->date, 0, 10) => (int) $row->count])
            ->all();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'count' => $rows[$day] ?? 0,
            ];
        }

        return $series;
    }
}
