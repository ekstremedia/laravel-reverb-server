<?php

use App\Models\WebsocketStat;
use Illuminate\Support\Carbon;

it('records the first occurrence of a metric for today', function () {
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);

    expect(WebsocketStat::todayFor(WebsocketStat::METRIC_MESSAGE_SENT))->toBe(1)
        ->and(WebsocketStat::totalFor(WebsocketStat::METRIC_MESSAGE_SENT))->toBe(1);
});

it('increments an existing row for the same metric and date', function () {
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);

    expect(WebsocketStat::todayFor(WebsocketStat::METRIC_MESSAGE_SENT))->toBe(3)
        ->and(WebsocketStat::query()->where('metric', WebsocketStat::METRIC_MESSAGE_SENT)->count())->toBe(1);
});

it('creates a separate row for each calendar day', function () {
    Carbon::setTestNow('2026-01-01 12:00:00');
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_RECEIVED);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_RECEIVED);

    Carbon::setTestNow('2026-01-02 12:00:00');
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_RECEIVED);

    expect(WebsocketStat::query()->where('metric', WebsocketStat::METRIC_MESSAGE_RECEIVED)->count())->toBe(2)
        ->and(WebsocketStat::totalFor(WebsocketStat::METRIC_MESSAGE_RECEIVED))->toBe(3)
        ->and(WebsocketStat::todayFor(WebsocketStat::METRIC_MESSAGE_RECEIVED))->toBe(1);
});

it('builds a 7-day series filling in zeros for missing days', function () {
    Carbon::setTestNow('2026-01-08 12:00:00');

    WebsocketStat::query()->create([
        'metric' => WebsocketStat::METRIC_MESSAGE_SENT,
        'date' => '2026-01-05',
        'count' => 4,
    ]);
    WebsocketStat::query()->create([
        'metric' => WebsocketStat::METRIC_MESSAGE_SENT,
        'date' => '2026-01-08',
        'count' => 2,
    ]);

    $series = WebsocketStat::lastDays(WebsocketStat::METRIC_MESSAGE_SENT, 7);

    expect($series)->toHaveCount(7)
        ->and($series[0])->toMatchArray(['date' => '2026-01-02', 'count' => 0])
        ->and($series[3])->toMatchArray(['date' => '2026-01-05', 'count' => 4])
        ->and($series[6])->toMatchArray(['date' => '2026-01-08', 'count' => 2]);
});
