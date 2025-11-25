<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Throwable;

class GetTelegramChatId extends Command
{
    protected $signature = 'telegram-log:get-chat-id
                            {--limit=10 : Maximum number of updates to fetch}
                            {--raw : Dump raw updates JSON}';

    protected $description = 'Fetch recent updates via getUpdates and display chat IDs (useful for group/channel IDs).';

    public function handle(): int
    {
        $botToken = config('telegram-log.bot_token');

        if (! $botToken) {
            $this->error('TELEGRAM_LOG_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $client = new Client([
            'base_uri' => "https://api.telegram.org/bot{$botToken}/",
            'timeout' => 10,
        ]);

        try {
            $response = $client->get('getUpdates', [
                'query' => [
                    'limit' => (int) $this->option('limit'),
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
        } catch (Throwable $e) {
            $this->error('Error calling getUpdates: '.$e->getMessage());
            $this->line('If you already have a webhook set, Telegram may not return updates via getUpdates.');
            $this->line('You can temporarily run: php artisan telegram-log:set-webhook --delete');

            return self::FAILURE;
        }

        if ($this->option('raw')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (! ($data['ok'] ?? false)) {
            $this->error('getUpdates failed: '.json_encode($data));

            return self::FAILURE;
        }

        if (empty($data['result'])) {
            $this->info('No updates found. Make sure you have sent a message to the bot (or in the group) recently.');

            return self::SUCCESS;
        }

        $this->info('Recent chat IDs:');

        $seen = [];

        foreach ($data['result'] as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? null;

            if (! $message) {
                continue;
            }

            $chat = $message['chat'] ?? null;

            if (! $chat) {
                continue;
            }

            $chatId = $chat['id'] ?? null;
            $type = $chat['type'] ?? 'unknown';
            $title = $chat['title'] ?? ($chat['username'] ?? '(no title)');

            if ($chatId === null) {
                continue;
            }

            if (isset($seen[$chatId])) {
                continue;
            }

            $seen[$chatId] = true;

            $this->line(sprintf(
                '- id: %s | type: %s | title: %s',
                $chatId,
                $type,
                $title,
            ));
        }

        if (empty($seen)) {
            $this->info('No chat IDs found in the latest updates.');
        } else {
            $this->info('Use one of these IDs as TELEGRAM_LOG_CHAT_ID in your .env.');
        }

        return self::SUCCESS;
    }
}
