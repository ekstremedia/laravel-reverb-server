<?php

use App\Events\PingEvent;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('api/stats', StatsController::class)->name('api.stats');

Route::post('api/ping', function (Request $request) {
    broadcast(new PingEvent(now()->toIso8601String()));

    Log::info('ping broadcast', ['ip' => $request->ip()]);

    return response()->noContent();
})->name('api.ping');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
