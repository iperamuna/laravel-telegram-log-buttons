<?php

namespace Iperamuna\TelegramLog;

use Illuminate\Support\ServiceProvider;
use Iperamuna\TelegramLog\Console\Commands\FlushTelegramLog;
use Iperamuna\TelegramLog\Console\Commands\GetTelegramChatId;
use Iperamuna\TelegramLog\Console\Commands\InstallTelegramLog;
use Iperamuna\TelegramLog\Console\Commands\ListTelegramCallbacks;
use Iperamuna\TelegramLog\Console\Commands\MakeTelegramCallback;
use Iperamuna\TelegramLog\Console\Commands\SetTelegramWebhook;
use Iperamuna\TelegramLog\Logging\MacroableLogger;
use Iperamuna\TelegramLog\Logging\TelegramButtonBuilder;
use Iperamuna\TelegramLog\Support\TelegramCallbackRegistry;
use Monolog\Logger;

class TelegramLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telegram-log.php', 'telegram-log');
        $this->mergeConfigFrom(__DIR__.'/../config/telegram-log-callbacks.php', 'telegram-log-callbacks');

        $this->app->singleton('telegram-log.callbacks', function () {
            return new TelegramCallbackRegistry;
        });

        // Auto-register callbacks from config (Octane/FrankenPHP compatible)
        // This runs once per worker lifecycle since registry is singleton
        $this->app->afterResolving('telegram-log.callbacks', function (TelegramCallbackRegistry $registry) {
            static $registered = false;

            if ($registered) {
                return;
            }

            $map = config('telegram-log-callbacks.map', []);

            if (is_array($map)) {
                foreach ($map as $action => $class) {
                    if (is_string($action) && is_string($class) && class_exists($class)) {
                        $registry->on($action, $class);
                    }
                }
            }

            $registered = true;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/telegram-log.php' => config_path('telegram-log.php'),
            __DIR__.'/../config/telegram-log-callbacks.php' => config_path('telegram-log-callbacks.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__.'/../views', 'telegram-log');

        $this->publishes([
            __DIR__.'/../views' => resource_path('views/vendor/telegram-log'),
        ], 'views');

        $this->loadRoutesFrom(__DIR__.'/../routes/telegram-log.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushTelegramLog::class,
                SetTelegramWebhook::class,
                GetTelegramChatId::class,
                MakeTelegramCallback::class,
                ListTelegramCallbacks::class,
                InstallTelegramLog::class,
            ]);
        }

        // Logger macros - Laravel's Macroable trait handles duplicate registration safely
        // These are registered statically and persist across requests (Octane/FrankenPHP compatible)
        if (! MacroableLogger::hasMacro('buttons')) {
            MacroableLogger::macro('buttons', function (): TelegramButtonBuilder {
                /** @var Logger $this */
                return new TelegramButtonBuilder($this);
            });
        }

        if (! MacroableLogger::hasMacro('addButton')) {
            MacroableLogger::macro('addButton', function (string $text, string $url) {
                /** @var Logger $this */
                $context = [
                    'buttons' => [
                        [
                            [
                                'text' => $text,
                                'url' => $url,
                            ],
                        ],
                    ],
                ];

                $logger = $this;

                return new class($logger, $context)
                {
                    public function __construct(
                        private Logger $logger,
                        private array $context,
                    ) {}

                    public function __call(string $method, array $args)
                    {
                        $message = $args[0] ?? '';
                        $extra = $args[1] ?? [];

                        $merged = array_merge_recursive($this->context, $extra);

                        return $this->logger->{$method}($message, $merged);
                    }
                };
            });
        }

        if (! MacroableLogger::hasMacro('addButtons')) {
            MacroableLogger::macro('addButtons', function (array $buttons) {
                /** @var Logger $this */
                $inline = [];

                foreach ($buttons as $button) {
                    $inline[] = [$button];
                }

                $context = ['buttons' => $inline];
                $logger = $this;

                return new class($logger, $context)
                {
                    public function __construct(
                        private Logger $logger,
                        private array $context,
                    ) {}

                    public function __call(string $method, array $args)
                    {
                        $message = $args[0] ?? '';
                        $extra = $args[1] ?? [];

                        $merged = array_merge_recursive($this->context, $extra);

                        return $this->logger->{$method}($message, $merged);
                    }
                };
            });
        }
    }
}
