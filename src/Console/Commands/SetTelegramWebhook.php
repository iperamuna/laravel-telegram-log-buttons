<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Throwable;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram-log:set-webhook
                            {--url= : Explicit webhook URL}
                            {--delete : Delete the current webhook instead of setting it}';

    protected $description = 'Set or delete the Telegram webhook for this bot, based on config/telegram-log.php and APP_URL.';

    public function handle(): int
    {
        $config = config('telegram-log');

        $botToken = $config['bot_token'] ?? null;
        $callbackConfig = $config['callback'] ?? [];

        if (! $botToken) {
            $this->error('TELEGRAM_LOG_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $client = new Client([
            'base_uri' => "https://api.telegram.org/bot{$botToken}/",
            'timeout' => 10,
        ]);

        if ($this->option('delete')) {
            try {
                $response = $client->post('deleteWebhook', [
                    'form_params' => [
                        'drop_pending_updates' => true,
                    ],
                ]);

                $data = json_decode((string) $response->getBody(), true);

                if (! ($data['ok'] ?? false)) {
                    $this->error('deleteWebhook failed: '.json_encode($data));

                    return self::FAILURE;
                }

                $this->info('Telegram webhook deleted successfully.');

                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->error('Error calling deleteWebhook: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $url = $this->option('url');

        if (! $url) {
            $appUrl = rtrim(config('app.url', ''), '/');
            $path = $callbackConfig['path'] ?? '/telegram/callback';

            if (! $appUrl) {
                $this->error('APP_URL is not set and no --url option provided.');

                return self::FAILURE;
            }

            $url = $appUrl.$path;
        }

        $this->info('Setting webhook to: '.$url);

        $params = ['url' => $url];

        if (! empty($callbackConfig['secret'])) {
            $params['secret_token'] = $callbackConfig['secret'];
        }

        try {
            $response = $client->post('setWebhook', [
                'form_params' => $params,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (! ($data['ok'] ?? false)) {
                $this->error('setWebhook failed: '.json_encode($data));

                return self::FAILURE;
            }

            $this->info('Telegram webhook set successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error calling setWebhook: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
