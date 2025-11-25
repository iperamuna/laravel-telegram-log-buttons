<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use Illuminate\Console\Command;

class ListTelegramCallbacks extends Command
{
    protected $signature = 'telegram-log:list-callbacks';

    protected $description = 'List all configured Telegram callback actions and their handler classes.';

    public function handle(): int
    {
        $map = config('telegram-log-callbacks.map', []);

        if (empty($map) || ! is_array($map)) {
            $this->info('No callbacks configured in config/telegram-log-callbacks.php.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($map as $action => $class) {
            if (! is_string($action) || ! is_string($class)) {
                continue;
            }

            $rows[] = [
                'action' => $action,
                'class' => $class,
                'exists' => class_exists($class) ? 'yes' : 'no',
            ];
        }

        if (empty($rows)) {
            $this->info('No valid callback mappings found.');

            return self::SUCCESS;
        }

        $this->table(['Action', 'Handler', 'Class exists'], $rows);

        $this->line('');
        $this->line('You can generate new callbacks using:');
        $this->line('  php artisan telegram-log:make-callback BanUser --action=ban_user');
        $this->line('');

        return self::SUCCESS;
    }
}
