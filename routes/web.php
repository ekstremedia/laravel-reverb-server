<?php

use App\Events\PingEvent;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('api/stats', StatsController::class)->name('api.stats');

Route::post('api/ping', function () {
    broadcast(new PingEvent(now()->toIso8601String()));

    return response()->noContent();
})->name('api.ping');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
