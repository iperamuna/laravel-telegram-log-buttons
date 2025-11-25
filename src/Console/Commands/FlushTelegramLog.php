<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Iperamuna\TelegramLog\Events\TelegramLogBatchFlushed;
use Throwable;

class FlushTelegramLog extends Command
{
    protected $signature = 'telegram-log:flush {--loop : Keep running, flushing and sleeping between batches}';

    protected $description = 'Flush buffered Telegram log messages from Redis and send them in grouped batches.';

    public function handle(): int
    {
        $config = config('telegram-log');
        $buffer = $config['buffer'] ?? [];
        $mode = $config['mode'] ?? 'instant';

        if ($mode !== 'buffered') {
            $this->warn('telegram-log mode is not "buffered" (current: '.$mode.'). Messages may not be enqueued.');
        }

        $botToken = $config['bot_token'] ?? null;
        $parseMode = $config['parse_mode'] ?? 'HTML';
        $chatId = $config['chat_id'] ?? null;

        if (! $botToken || ! $chatId) {
            $this->error('Telegram bot_token or chat_id is not configured.');

            return self::FAILURE;
        }

        $connectionName = $buffer['redis_connection'] ?? null;
        $key = $buffer['redis_key'] ?? 'telegram_log:queue';
        $maxBatch = (int) ($buffer['max_batch'] ?? 20);
        $maxLen = (int) ($buffer['max_message_len'] ?? 3500);

        /** @var \Illuminate\Contracts\Redis\Factory $redisFactory */
        $redisFactory = app('redis');

        $redis = $connectionName
            ? $redisFactory->connection($connectionName)
            : $redisFactory->connection();

        $client = new Client([
            'base_uri' => "https://api.telegram.org/bot{$botToken}/",
            'timeout' => 5,
        ]);

        $loop = (bool) $this->option('loop');

        do {
            $batch = [];

            for ($i = 0; $i < $maxBatch; $i++) {
                $raw = $redis->lpop($key);
                if (! $raw) {
                    break;
                }

                $item = json_decode($raw, true);
                if (! is_array($item)) {
                    continue;
                }

                $batch[] = $item;
            }

            if (empty($batch)) {
                if (! $loop) {
                    $this->info('No buffered Telegram log messages to flush.');

                    return self::SUCCESS;
                }

                usleep(500_000);

                continue;
            }

            $lines = [];
            $inlineKeyboard = null;

            foreach ($batch as $entry) {
                $level = $entry['level'] ?? null;
                $time = $entry['time'] ?? null;
                $text = $entry['text'] ?? '';

                $prefix = $time ? "[{$time}]" : '';
                if ($level) {
                    $prefix .= $prefix ? " [{$level}]" : "[{$level}]";
                }

                $lines[] = trim($prefix.' '.$text);

                if (isset($entry['buttons']) && is_array($entry['buttons'])) {
                    $inlineKeyboard = $entry['buttons'];
                }
            }

            $body = implode("\n\n", $lines);

            if (mb_strlen($body) > $maxLen) {
                $body = mb_substr($body, 0, $maxLen)."\n\n...[truncated]";
            }

            $payload = [
                'chat_id' => $chatId,
                'text' => $body,
                'parse_mode' => $parseMode,
            ];

            if ($inlineKeyboard) {
                $payload['reply_markup'] = json_encode([
                    'inline_keyboard' => $inlineKeyboard,
                ]);
            }

            try {
                $client->post('sendMessage', [
                    'form_params' => $payload,
                ]);

                $count = count($batch);
                $this->info('Flushed '.$count.' Telegram log entries.');

                event(new TelegramLogBatchFlushed(
                    entries: $batch,
                    body: $body,
                    count: $count,
                    chatId: $chatId,
                    parseMode: $parseMode,
                ));
            } catch (Throwable $e) {
                $this->error('Error sending Telegram log batch: '.$e->getMessage());
            }

            if ($loop) {
                sleep(30);
            }

        } while ($loop);

        return self::SUCCESS;
    }
}
