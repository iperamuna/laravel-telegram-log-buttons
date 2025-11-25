<?php

namespace Iperamuna\TelegramLog\Logging;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\View;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Throwable;

class TelegramButtonHandler extends AbstractProcessingHandler
{
    protected string $botToken;

    protected string|int $chatId;

    protected ?string $defaultButtonText;

    protected ?string $defaultButtonUrl;

    protected string $parseMode;

    protected string $template;

    protected Client $client;

    public function __construct(
        ?string $botToken = null,
        string|int|null $chatId = null,
        ?string $defaultButtonText = null,
        ?string $defaultButtonUrl = null,
        ?string $parseMode = null,
        ?string $template = null,
        int $level = Logger::DEBUG,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $config = config('telegram-log', []);

        $this->botToken = $botToken ?? (string) ($config['bot_token'] ?? '');
        $this->chatId = $chatId ?? (string) ($config['chat_id'] ?? '');
        $this->defaultButtonText = $defaultButtonText ?? ($config['default_button_text'] ?? null);
        $this->defaultButtonUrl = $defaultButtonUrl ?? ($config['default_button_url'] ?? null);
        $this->parseMode = $parseMode ?? ($config['parse_mode'] ?? 'HTML');
        $this->template = $template ?? ($config['template'] ?? 'telegram-log::standard');

        $this->client = new Client([
            'base_uri' => "https://api.telegram.org/bot{$this->botToken}/",
            'timeout' => 5,
        ]);
    }

    /**
     * @param  array<string,mixed>  $record
     */
    protected function write(array $record): void
    {
        // Extract buttons from context before formatting
        $context = $record['context'] ?? [];
        $buttons = $context['buttons'] ?? null;
        unset($context['buttons']);

        // Format message using template
        $text = $this->formatMessage($record, $context);

        $mode = config('telegram-log.mode', 'instant');
        $bufferConfig = config('telegram-log.buffer', []);
        $bufferEnabled = (bool) ($bufferConfig['enabled'] ?? true);

        $inlineKeyboard = null;

        if (is_array($buttons) && ! empty($buttons)) {
            $inlineKeyboard = $buttons;
        } elseif ($this->defaultButtonText && $this->defaultButtonUrl) {
            $inlineKeyboard = [
                [
                    [
                        'text' => $this->defaultButtonText,
                        'url' => $this->defaultButtonUrl,
                    ],
                ],
            ];
        }

        if ($mode === 'buffered' && $bufferEnabled) {
            try {
                /** @var \Illuminate\Contracts\Redis\Factory $redisFactory */
                $redisFactory = app('redis');

                $connectionName = $bufferConfig['redis_connection'] ?? null;
                $key = $bufferConfig['redis_key'] ?? 'telegram_log:queue';

                $redis = $connectionName
                    ? $redisFactory->connection($connectionName)
                    : $redisFactory->connection();

                $payload = [
                    'chat_id' => $this->chatId,
                    'text' => $text,
                    'parseMode' => $this->parseMode,
                    'buttons' => $inlineKeyboard,
                    'time' => now()->toIso8601String(),
                    'level' => $record['level_name'] ?? null,
                ];

                $redis->rpush($key, json_encode($payload));
            } catch (Throwable $e) {
                // swallow
            }

            return;
        }

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => $this->parseMode,
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        }

        try {
            $this->client->post('sendMessage', [
                'form_params' => $payload,
            ]);
        } catch (Throwable $e) {
            // swallow
        }
    }

    /**
     * Format the log message using the configured template.
     *
     * @param  array<string,mixed>  $record
     * @param  array<string,mixed>  $context
     */
    protected function formatMessage(array $record, array $context): string
    {
        try {
            // Check if view exists, fallback to simple message if not
            if (! View::exists($this->template)) {
                return $record['formatted'] ?? (string) ($record['message'] ?? '');
            }

            $extra = $record['extra'] ?? [];
            $datetime = isset($record['datetime']) && $record['datetime'] instanceof \DateTimeInterface
                ? $record['datetime']->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            return View::make($this->template, [
                'level' => $record['level_name'] ?? 'INFO',
                'message' => $record['formatted'] ?? (string) ($record['message'] ?? ''),
                'context' => $context,
                'extra' => $extra,
                'datetime' => $datetime,
                'environment' => config('app.env', 'production'),
            ])->render();
        } catch (Throwable $e) {
            // Fallback to formatted message if view rendering fails
            return $record['formatted'] ?? (string) ($record['message'] ?? '');
        }
    }
}
