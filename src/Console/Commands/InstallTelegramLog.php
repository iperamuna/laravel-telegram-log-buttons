<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class InstallTelegramLog extends Command
{
    protected $signature = 'telegram-log:install';

    protected $description = 'Interactive setup wizard for Telegram Log Buttons package.';

    protected Client $client;

    public function handle(): int
    {
        $this->info('🚀 Welcome to Telegram Log Buttons Setup!');
        $this->newLine();

        // Step 1: Get Bot Token
        $botToken = $this->getBotToken();

        if (! $botToken) {
            $this->error('Bot token is required. Setup cancelled.');

            return self::FAILURE;
        }

        $this->client = new Client([
            'base_uri' => "https://api.telegram.org/bot{$botToken}/",
            'timeout' => 10,
        ]);

        // Step 2: Verify bot token
        if (! $this->verifyBotToken($botToken)) {
            $this->error('Invalid bot token. Please check and try again.');

            return self::FAILURE;
        }

        info('✅ Bot token verified!');
        $this->newLine();

        // Step 3: Get Chat ID
        $chatId = $this->getChatId($botToken);

        if (! $chatId) {
            $this->error('Chat ID is required. Setup cancelled.');

            return self::FAILURE;
        }

        // Step 4: Set up webhook
        $webhookResult = $this->setupWebhook($botToken);
        $webhookEnabled = $webhookResult['enabled'];
        $webhookSecret = $webhookResult['secret'] ?? null;

        // Step 5: Health check configuration
        $healthEnabled = confirm(
            label: 'Enable health check endpoint?',
            default: false,
            hint: 'This allows monitoring tools to check the Telegram log configuration status.'
        );

        $healthSecret = null;
        if ($healthEnabled) {
            $healthSecret = text(
                label: 'Health check secret (optional, for security)',
                placeholder: 'Leave empty for no secret',
                default: '',
                hint: 'If set, requests must include X-Telegram-Log-Health-Secret header'
            );
            $healthSecret = $healthSecret ?: null;
        }

        // Step 6: Mode selection
        $mode = select(
            label: 'Select logging mode',
            options: [
                'instant' => 'Instant - Send messages immediately',
                'buffered' => 'Buffered - Batch messages using Redis (requires Redis)',
            ],
            default: 'instant',
            hint: 'Buffered mode groups messages for better performance'
        );

        // Step 7: Update .env file
        $envUpdates = [
            'TELEGRAM_LOG_BOT_TOKEN' => $botToken,
            'TELEGRAM_LOG_CHAT_ID' => $chatId,
            'TELEGRAM_LOG_CALLBACK_ENABLED' => $webhookEnabled ? 'true' : 'false',
            'TELEGRAM_LOG_HEALTH_ENABLED' => $healthEnabled ? 'true' : 'false',
            'TELEGRAM_LOG_BUFFER_MODE' => $mode,
        ];

        if ($webhookSecret) {
            $envUpdates['TELEGRAM_LOG_CALLBACK_SECRET'] = $webhookSecret;
        }

        if ($healthSecret) {
            $envUpdates['TELEGRAM_LOG_HEALTH_SECRET'] = $healthSecret;
        }

        $this->updateEnvFile($envUpdates);

        $this->newLine();
        info('✅ Setup complete!');
        $this->newLine();

        $this->displaySummary([
            'bot_token' => Str::mask($botToken, '*', 10),
            'chat_id' => $chatId,
            'webhook_enabled' => $webhookEnabled,
            'health_enabled' => $healthEnabled,
            'mode' => $mode,
        ]);

        $this->newLine();
        note('Next steps:');
        $this->line('  1. Configure your logging channel in config/logging.php');
        $this->line('  2. Start using: Log::channel(\'telegram\')->buttons()->info(\'Hello!\');');
        $this->line('  3. Generate callbacks: php artisan telegram-log:make-callback');

        return self::SUCCESS;
    }

    protected function getBotToken(): string
    {
        $currentToken = env('TELEGRAM_LOG_BOT_TOKEN');

        if ($currentToken) {
            $useCurrent = confirm(
                label: 'Found existing bot token. Use it?',
                default: true
            );

            if ($useCurrent) {
                return $currentToken;
            }
        }

        $this->newLine();
        note('To get a bot token:');
        $this->line('  1. Open Telegram and search for @BotFather');
        $this->line('  2. Send /newbot command');
        $this->line('  3. Follow the instructions to create your bot');
        $this->line('  4. Copy the bot token provided');
        $this->newLine();

        return text(
            label: 'Enter your Telegram bot token',
            placeholder: '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            required: true,
            hint: 'Get it from @BotFather on Telegram'
        );
    }

    protected function verifyBotToken(string $botToken): bool
    {
        try {
            $response = $this->client->get('getMe');
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['ok'] ?? false) {
                $bot = $data['result'] ?? [];
                info("Bot verified: @{$bot['username']} ({$bot['first_name']})");

                return true;
            }
        } catch (\Throwable $e) {
            // Fall through to return false
        }

        return false;
    }

    protected function getChatId(string $botToken): ?string
    {
        $currentChatId = env('TELEGRAM_LOG_CHAT_ID');

        if ($currentChatId) {
            $useCurrent = confirm(
                label: "Found existing chat ID ({$currentChatId}). Use it?",
                default: true
            );

            if ($useCurrent) {
                return $currentChatId;
            }
        }

        // Check for webhook before proceeding
        $webhookInfo = $this->getWebhookInfo();
        $hasWebhook = $webhookInfo && ! empty($webhookInfo['url']);

        if ($hasWebhook) {
            warning('⚠️  A webhook is already configured!');
            $this->newLine();
            $this->line("Current webhook URL: {$webhookInfo['url']}");
            $this->newLine();
            note('Telegram won\'t send updates via getUpdates when a webhook is active.');
            note('This means auto-detection of chat ID may not work.');
            $this->newLine();
        }

        $this->newLine();
        note('To get your chat ID:');
        $this->line('  1. Add your bot to a Telegram group (or use a personal chat)');
        $this->line('  2. Send a message in the group');
        if (! $hasWebhook) {
            $this->line('  3. We\'ll fetch the chat ID automatically');
        } else {
            $this->line('  3. We\'ll try to fetch the chat ID (may require deleting webhook temporarily)');
        }
        $this->newLine();

        $method = select(
            label: 'How do you want to get the chat ID?',
            options: [
                'auto' => 'Auto-detect from recent updates'.($hasWebhook ? ' (may not work with webhook)' : ''),
                'manual' => 'Enter manually',
            ],
            default: $hasWebhook ? 'manual' : 'auto'
        );

        if ($method === 'auto') {
            return $this->autoDetectChatId($botToken, $webhookInfo);
        }

        return text(
            label: 'Enter your Telegram chat ID',
            placeholder: '123456789 or -1001234567890',
            required: true,
            hint: 'For groups, it starts with -100'
        );
    }

    protected function autoDetectChatId(string $botToken, ?array $webhookInfo = null): ?string
    {
        // Use provided webhook info or fetch it
        if ($webhookInfo === null) {
            $webhookInfo = $this->getWebhookInfo();
        }

        $webhookToRestore = null;
        $webhookSecretToRestore = null;

        if ($webhookInfo && ! empty($webhookInfo['url'])) {
            warning('⚠️  A webhook is already configured!');
            $this->newLine();
            $this->line("Current webhook URL: {$webhookInfo['url']}");
            $this->newLine();
            note('Telegram won\'t send updates via getUpdates when a webhook is active.');
            $this->newLine();

            $action = select(
                label: 'What would you like to do?',
                options: [
                    'delete' => 'Temporarily delete webhook to fetch chat ID (will restore automatically)',
                    'manual' => 'Enter chat ID manually',
                    'skip' => 'Skip chat ID setup (use existing from .env)',
                ],
                default: 'manual'
            );

            if ($action === 'delete') {
                // Store webhook info to restore later
                $webhookToRestore = $webhookInfo['url'];
                $webhookSecretToRestore = $webhookInfo['secret_token'] ?? null;

                try {
                    $response = $this->client->post('deleteWebhook', [
                        'form_params' => [
                            'drop_pending_updates' => false,
                        ],
                    ]);

                    $data = json_decode($response->getBody()->getContents(), true);

                    if ($data['ok'] ?? false) {
                        info('✅ Webhook deleted temporarily. It will be restored automatically after fetching chat ID.');
                        $this->newLine();
                    } else {
                        warning('Failed to delete webhook. Please enter chat ID manually.');

                        return text(
                            label: 'Enter your Telegram chat ID',
                            placeholder: '123456789 or -1001234567890',
                            required: true
                        );
                    }
                } catch (\Throwable $e) {
                    warning('Error deleting webhook: '.$e->getMessage());

                    return text(
                        label: 'Enter your Telegram chat ID',
                        placeholder: '123456789 or -1001234567890',
                        required: true
                    );
                }
            } elseif ($action === 'skip') {
                $existingChatId = env('TELEGRAM_LOG_CHAT_ID');

                if ($existingChatId) {
                    return $existingChatId;
                }

                warning('No existing chat ID found. Please enter manually.');

                return text(
                    label: 'Enter your Telegram chat ID',
                    placeholder: '123456789 or -1001234567890',
                    required: true
                );
            }
        }

        info('Fetching recent updates...');

        try {
            $response = $this->client->get('getUpdates', [
                'query' => ['limit' => 10],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! ($data['ok'] ?? false)) {
                warning('Could not fetch updates. Please enter chat ID manually.');

                // Restore webhook if it was temporarily deleted
                if ($webhookToRestore) {
                    $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
                }

                return text(
                    label: 'Enter your Telegram chat ID',
                    placeholder: '123456789 or -1001234567890',
                    required: true
                );
            }

            $updates = $data['result'] ?? [];

            if (empty($updates)) {
                warning('No recent updates found. Make sure you\'ve sent a message to the bot.');
                $this->newLine();
                note('Steps to get updates:');
                $this->line('  1. Add your bot to a Telegram group');
                $this->line('  2. Send a message in the group');
                $this->line('  3. Run this command again');
                $this->newLine();

                $tryManual = confirm('Enter chat ID manually instead?', default: true);

                // Restore webhook if it was temporarily deleted
                if ($webhookToRestore) {
                    $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
                }

                if ($tryManual) {
                    return text(
                        label: 'Enter your Telegram chat ID',
                        placeholder: '123456789 or -1001234567890',
                        required: true
                    );
                }

                return null;
            }

            // Extract unique chat IDs
            $chatIds = [];
            foreach ($updates as $update) {
                if (isset($update['message']['chat']['id'])) {
                    $chatId = (string) $update['message']['chat']['id'];
                    $chatTitle = $update['message']['chat']['title'] ?? 'Personal Chat';
                    $chatType = $update['message']['chat']['type'] ?? 'private';

                    if (! isset($chatIds[$chatId])) {
                        $chatIds[$chatId] = [
                            'id' => $chatId,
                            'title' => $chatTitle,
                            'type' => $chatType,
                        ];
                    }
                }
            }

            if (empty($chatIds)) {
                warning('No chat IDs found in updates.');

                // Restore webhook if it was temporarily deleted
                if ($webhookToRestore) {
                    $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
                }

                return text(
                    label: 'Enter your Telegram chat ID',
                    placeholder: '123456789 or -1001234567890',
                    required: true
                );
            }

            if (count($chatIds) === 1) {
                $chat = reset($chatIds);
                info("Found chat: {$chat['title']} ({$chat['type']})");

                $chatId = $chat['id'];

                // Restore webhook if it was temporarily deleted
                if ($webhookToRestore) {
                    $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
                }

                return $chatId;
            }

            // Multiple chats found - let user choose
            $options = [];
            foreach ($chatIds as $chat) {
                $label = "{$chat['title']} ({$chat['type']}) - ID: {$chat['id']}";
                $options[$chat['id']] = $label;
            }

            $chatId = select(
                label: 'Multiple chats found. Select one:',
                options: $options,
                required: true
            );

            // Restore webhook if it was temporarily deleted
            if ($webhookToRestore) {
                $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
            }

            return $chatId;
        } catch (\Throwable $e) {
            warning('Error fetching updates: '.$e->getMessage());

            // Restore webhook even on error if it was deleted
            if ($webhookToRestore) {
                $this->restoreWebhook($webhookToRestore, $webhookSecretToRestore);
            }

            return text(
                label: 'Enter your Telegram chat ID',
                placeholder: '123456789 or -1001234567890',
                required: true
            );
        }
    }

    protected function restoreWebhook(string $url, ?string $secret = null): void
    {
        try {
            $params = ['url' => $url];

            if ($secret) {
                $params['secret_token'] = $secret;
            }

            $response = $this->client->post('setWebhook', [
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['ok'] ?? false) {
                info('✅ Webhook restored successfully!');
            } else {
                warning('Failed to restore webhook: '.($data['description'] ?? 'Unknown error'));
                $this->line("Please restore it manually: {$url}");
            }
        } catch (\Throwable $e) {
            warning('Error restoring webhook: '.$e->getMessage());
            $this->line("Please restore it manually: {$url}");
        }
    }

    protected function getWebhookInfo(): ?array
    {
        try {
            $response = $this->client->get('getWebhookInfo');
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['ok'] ?? false) {
                return $data['result'] ?? null;
            }
        } catch (\Throwable $e) {
            // Ignore errors, return null
        }

        return null;
    }

    protected function setupWebhook(string $botToken): array
    {
        $this->newLine();
        $enableWebhook = confirm(
            label: 'Set up callback webhook?',
            default: true,
            hint: 'Required for handling button callbacks'
        );

        if (! $enableWebhook) {
            return ['enabled' => false];
        }

        $appUrl = config('app.url');

        if (empty($appUrl) || $appUrl === 'http://localhost') {
            warning('APP_URL is not configured or is localhost.');
            $appUrl = text(
                label: 'Enter your application URL',
                placeholder: 'https://your-app.com',
                required: true,
                hint: 'This will be used for the webhook URL'
            );
        }

        $webhookPath = config('telegram-log.callback.path', '/telegram/callback');
        $webhookUrl = rtrim($appUrl, '/').$webhookPath;

        $setSecret = confirm(
            label: 'Set webhook secret for security?',
            default: false,
            hint: 'Recommended for production'
        );

        $secret = null;
        if ($setSecret) {
            $secret = text(
                label: 'Webhook secret',
                placeholder: 'Leave empty to generate random',
                default: '',
                hint: 'Must be included in X-Telegram-Callback-Secret header'
            );

            if (empty($secret)) {
                $secret = Str::random(32);
                info("Generated secret: {$secret}");
            }
        }

        info("Setting webhook to: {$webhookUrl}");

        try {
            $params = ['url' => $webhookUrl];

            if ($secret) {
                $params['secret_token'] = $secret;
            }

            $response = $this->client->post('setWebhook', [
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['ok'] ?? false) {
                info('✅ Webhook set successfully!');

                return [
                    'enabled' => true,
                    'secret' => $secret,
                ];
            } else {
                warning('Failed to set webhook: '.($data['description'] ?? 'Unknown error'));

                return ['enabled' => false];
            }
        } catch (\Throwable $e) {
            warning('Error setting webhook: '.$e->getMessage());

            return ['enabled' => false];
        }
    }

    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            warning('.env file not found. Please add these values manually:');
            foreach ($values as $key => $value) {
                $this->line("  {$key}={$value}");
            }

            return;
        }

        $envContent = file_get_contents($envPath);
        $updated = false;

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*/m";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
                $updated = true;
            } else {
                // Add new entry
                $envContent .= "\n{$key}={$value}\n";
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($envPath, $envContent);
            info('✅ .env file updated');
        }
    }

    protected function displaySummary(array $config): void
    {
        info('Configuration Summary:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Bot Token', $config['bot_token']],
                ['Chat ID', $config['chat_id']],
                ['Webhook Enabled', $config['webhook_enabled'] ? 'Yes' : 'No'],
                ['Health Check Enabled', $config['health_enabled'] ? 'Yes' : 'No'],
                ['Mode', $config['mode']],
            ]
        );
    }
}
