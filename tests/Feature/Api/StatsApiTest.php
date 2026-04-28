<?php

use App\Models\WebsocketStat;

it('exposes stats over a public json endpoint', function () {
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_SENT);
    WebsocketStat::record(WebsocketStat::METRIC_MESSAGE_RECEIVED);

    $this->getJson(route('api.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'checked_at',
            'reverb' => ['host', 'port', 'scheme'],
            'totals' => [
                'message_sent' => ['today', 'total'],
                'message_received' => ['today', 'total'],
                'channel_created',
                'channel_removed',
                'connection_pruned',
            ],
            'last_7_days' => [
                'message_sent',
                'message_received',
                'channel_created',
                'channel_removed',
                'connection_pruned',
            ],
        ])
        ->assertJsonPath('totals.message_sent.total', 2)
        ->assertJsonPath('totals.message_received.today', 1);
});

it('returns offline status when reverb is unreachable', function () {
    config()->set('broadcasting.connections.reverb.options.host', '127.0.0.1');
    config()->set('broadcasting.connections.reverb.options.port', 1);

    $this->getJson(route('api.stats'))
        ->assertOk()
        ->assertJsonPath('status', 'offline');
});

it('builds a 7-day series of length 7 for each metric', function () {
    $response = $this->getJson(route('api.stats'))->assertOk()->json('last_7_days');

    foreach (WebsocketStat::METRICS as $metric) {
        expect($response[$metric])->toHaveCount(7);
    }
});

it('does not require authentication', function () {
    $this->getJson(route('api.stats'))->assertOk();
});
