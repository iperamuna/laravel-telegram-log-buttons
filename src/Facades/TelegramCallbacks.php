<?php

namespace Iperamuna\TelegramLog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Iperamuna\TelegramLog\Support\TelegramCallbackRegistry on(string $action, \Closure|string $handler)
 * @method static array all()
 *
 * @see \Iperamuna\TelegramLog\Support\TelegramCallbackRegistry
 */
class TelegramCallbacks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'telegram-log.callbacks';
    }
}
