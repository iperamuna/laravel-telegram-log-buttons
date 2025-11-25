<?php

use Illuminate\Support\Facades\Route;
use Iperamuna\TelegramLog\Http\Controllers\HealthCheckController;
use Iperamuna\TelegramLog\Http\Controllers\TelegramCallbackController;

Route::post(config('telegram-log.callback.path', '/telegram/callback'), TelegramCallbackController::class)
    ->name('telegram-log.callback');

if (config('telegram-log.health.enabled')) {
    Route::get(config('telegram-log.health.path', '/telegram/log/health'), HealthCheckController::class)
        ->name('telegram-log.health');
}
