<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Callback Handler Map
    |--------------------------------------------------------------------------
    |
    | Map callback action names to their handler classes.
    |
    | Handlers should extend AbstractTelegramCallbackHandler and implement
    | handleParsed($update, $action, $payload) method.
    |
    | Example:
    |   'ban_user' => \App\Telegram\Callbacks\BanUserCallback::class,
    |
    | The handler will receive:
    |   - $update: Full Telegram update payload
    |   - $action: The action name (e.g. "ban_user")
    |   - $payload: The payload after the colon (e.g. "5" from "ban_user:5")
    |
    */

    'map' => [
        // 'ban_user' => \App\Telegram\Callbacks\BanUserCallback::class,
    ],

];
