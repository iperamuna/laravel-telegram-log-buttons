<?php

namespace Iperamuna\TelegramLog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class MakeTelegramCallback extends Command
{
    protected $signature = 'telegram-log:make-callback
                            {name? : The class base name (without namespace), e.g. BanUser}
                            {--action= : The callback action name (defaults to snake_case of name, e.g. ban_user)}';

    protected $description = 'Generate a Telegram callback handler stub (extending AbstractTelegramCallbackHandler) in App\Telegram\Callbacks and register it in config/telegram-log-callbacks.php.';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $name = text(
                label: 'Callback class base name (without namespace)',
                placeholder: 'BanUser',
                required: true
            );
        }

        $action = $this->option('action') ?: Str::snake($name);

        if (! $this->option('action')) {
            $action = text(
                label: 'Callback action name (used before ":" in callback_data)',
                placeholder: Str::snake($name),
                default: $action,
                required: true
            );
        }

        $namespace = 'App\\Telegram\\Callbacks';
        $className = Str::finish($name, 'Callback');

        $this->info("Class  : {$namespace}\\{$className}");
        $this->info("Action : {$action}");
        $this->newLine();

        if (! confirm('Generate this callback handler?', default: true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $relativePath = 'app/Telegram/Callbacks/'.$className.'.php';
        $path = base_path($relativePath);

        if ($files->exists($path)) {
            $this->error("Callback handler already exists: {$relativePath}");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));

        $stub = $this->buildStub($namespace, $className, $action);
        $files->put($path, $stub);

        $this->info("Created callback handler: {$relativePath}");

        $configPath = config_path('telegram-log-callbacks.php');

        if (! $files->exists($configPath)) {
            $this->warn('config/telegram-log-callbacks.php does not exist, publishing a fresh one.');

            $packageConfig = __DIR__.'/../../../config/telegram-log-callbacks.php';
            $files->copy($packageConfig, $configPath);
        }

        $this->registerInConfig($files, $configPath, $action, $namespace.'\\'.$className);

        $this->info("Registered callback action '{$action}' => {$namespace}\\{$className} in config/telegram-log-callbacks.php.");

        $this->newLine();
        $this->line('Use this callback_data in your buttons:');
        $this->line("  {$action}:<payload>");
        $this->newLine();
        $this->line('Example:');
        $this->line("  ->callback('Do something', '{$action}:123')");
        $this->newLine();

        return self::SUCCESS;
    }

    protected function buildStub(string $namespace, string $className, string $action): string
    {
        return <<<PHP
        <?php

        namespace {$namespace};

        use Iperamuna\\TelegramLog\\Logging\\AbstractTelegramCallbackHandler;

        /**
         * Telegram callback handler for action "{$action}".
         *
         * callback_data convention:
         *   "{$action}:<payload>"
         * Example:
         *   "{$action}:5"
         */
        class {$className} extends AbstractTelegramCallbackHandler
        {
            /**
             * Handle a parsed Telegram callback_query update.
             *
             * @param  array<string,mixed>  \$update  Full Telegram update payload
             * @param  string  \$action  The action name (extracted from callback_data)
             * @param  string  \$payload  The payload after the colon (extracted from callback_data)
             */
            protected function handleParsed(array \$update, string \$action, string \$payload): void
            {
                // TODO: Implement your logic here.
                // e.g. find a model, perform an action, log something, etc.
                //
                // You have access to:
                //   - \$update: Full Telegram update payload
                //   - \$action: The action name ("{$action}")
                //   - \$payload: The payload value (e.g. "5" from "{$action}:5")
            }
        }

        PHP;
    }

    protected function registerInConfig(Filesystem $files, string $configPath, string $action, string $fqcn): void
    {
        $contents = $files->get($configPath);

        // Ensure FQCN has leading backslash
        $fqcn = ltrim($fqcn, '\\');
        $fqcnWithBackslash = '\\'.$fqcn;

        $pattern = "/('map'\s*=>\s*\[)(.*?)(\n\s*\],)/s";

        if (! preg_match($pattern, $contents, $matches)) {
            $mapping = "    'map' => [
        '{$action}' => {$fqcnWithBackslash}::class,
    ],
";
            $contents = "<?php

return [
{$mapping}];
";
        } else {
            $before = $matches[1];
            $middle = $matches[2];
            $after = $matches[3];

            if (str_contains($middle, "'{$action}'")) {
                return;
            }

            $insertion = "\n        '{$action}' => {$fqcnWithBackslash}::class,";

            $middle .= $insertion;

            $contents = preg_replace($pattern, $before.$middle.$after, $contents);
        }

        $files->put($configPath, $contents);
    }
}
