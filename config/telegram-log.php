<?php

return [

    'bot_token' => env('TELEGRAM_LOG_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_LOG_CHAT_ID'),

    'default_button_text' => env('TELEGRAM_LOG_BUTTON_TEXT', null),
    'default_button_url' => env('TELEGRAM_LOG_BUTTON_URL', null),

    'parse_mode' => env('TELEGRAM_LOG_PARSE_MODE', 'HTML'),

    // Template view for formatting log messages
    // Available: 'telegram-log::standard', 'telegram-log::minimal', or custom view path
    'template' => env('TELEGRAM_LOG_TEMPLATE', 'telegram-log::standard'),

    // Mode: instant | buffered
    'mode' => env('TELEGRAM_LOG_BUFFER_MODE', 'instant'),

    'buffer' => [
        'enabled' => env('TELEGRAM_LOG_BUFFER_ENABLED', true),
        'redis_connection' => env('TELEGRAM_LOG_BUFFER_REDIS_CONNECTION', null),
        'redis_key' => env('TELEGRAM_LOG_BUFFER_REDIS_KEY', 'telegram_log:queue'),
        'max_batch' => env('TELEGRAM_LOG_BUFFER_MAX_BATCH', 20),
        'max_message_len' => env('TELEGRAM_LOG_BUFFER_MAX_MESSAGE_LEN', 3500),
    ],

    'callback' => [
        'enabled' => env('TELEGRAM_LOG_CALLBACK_ENABLED', true),
        'path' => env('TELEGRAM_LOG_CALLBACK_PATH', '/telegram/callback'),
        'secret' => env('TELEGRAM_LOG_CALLBACK_SECRET', null),
    ],

    'health' => [
        'enabled' => env('TELEGRAM_LOG_HEALTH_ENABLED', false),
        'path' => env('TELEGRAM_LOG_HEALTH_PATH', '/telegram/log/health'),
        // Optional header-based secret: X-Telegram-Log-Health-Secret
        'secret' => env('TELEGRAM_LOG_HEALTH_SECRET', null),
    ],
];
