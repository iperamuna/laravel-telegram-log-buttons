# Laravel Telegram Log Buttons

A Laravel package to send **Telegram log messages with inline buttons** via Monolog, with:

- Instant / buffered modes (Redis-backed batch sender)
- `TelegramLogBatchFlushed` event for analytics
- Config-driven callback routing (`config/telegram-log-callbacks.php`)
- **Artisan callback generator** using Laravel Prompts
- Helper commands for webhooks & chat IDs
- Optional **health check route**
- **Customizable message templates** (inspired by [grkamil/laravel-telegram-logging](https://github.com/grkamil/laravel-telegram-logging))
- Pest tests via Orchestra Testbench

> **Note:** The template/view mechanism for formatting log messages is inspired by [grkamil/laravel-telegram-logging](https://github.com/grkamil/laravel-telegram-logging). Credit goes to the original author for this excellent feature.

---

## Installation

```bash
composer require iperamuna/laravel-telegram-log-buttons
```

### Quick Setup (Recommended)

Run the interactive setup wizard:

```bash
php artisan telegram-log:install
```

This command will:
1. ✅ Ask for your Telegram bot token (or use existing)
2. ✅ Verify the bot token with Telegram
3. ✅ Guide you through connecting to a Telegram group/chat
4. ✅ Auto-detect chat ID from recent updates
5. ✅ Set up the callback webhook automatically
6. ✅ Configure health check endpoint (optional)
7. ✅ Select logging mode (instant/buffered)
8. ✅ Update your `.env` file with all necessary values

### Manual Setup

Publish config:

```bash
php artisan vendor:publish --provider="Iperamuna\\TelegramLog\\TelegramLogServiceProvider" --tag="config"
```

This publishes:

- `config/telegram-log.php`
- `config/telegram-log-callbacks.php`

---

## Configuration

Configure your Telegram bot token and chat ID in `.env`:

```env
TELEGRAM_LOG_BOT_TOKEN=your_bot_token_here
TELEGRAM_LOG_CHAT_ID=your_chat_id_here
```

These are automatically loaded from `config/telegram-log.php`:

```php
'bot_token' => env('TELEGRAM_LOG_BOT_TOKEN'),
'chat_id' => env('TELEGRAM_LOG_CHAT_ID'),
```

Add the Telegram log channel to `config/logging.php`:

```php
'channels' => [
    // ... other channels
    
    'telegram' => [
        'driver' => 'monolog',
        'handler' => \Iperamuna\TelegramLog\Logging\TelegramButtonHandler::class,
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

---

## Usage

### Telegram Log Buttons

This package extends Laravel's logging system to send messages with inline buttons to Telegram. You can add buttons using three convenient methods:

#### Method 1: Using `buttons()` Macro (Fluent Builder)

The `buttons()` macro returns a `TelegramButtonBuilder` instance for fluent button creation:

```php
use Illuminate\Support\Facades\Log;

Log::channel('telegram')
    ->buttons()
    ->url('View Dashboard', 'https://example.com/dashboard')
    ->callback('Approve', 'approve:123')
    ->newRow()
    ->callback('Reject', 'reject:123')
    ->info('New user registration requires approval');
```

**Available methods:**
- `url($text, $url)` - Add a URL button
- `callback($text, $callbackData)` - Add a callback button
- `newRow()` - Start a new row of buttons
- `withContext($context)` - Add extra context data
- `info($message, $context = [])` - Send as info level
- `warning($message, $context = [])` - Send as warning level
- `error($message, $context = [])` - Send as error level
- `critical($message, $context = [])` - Send as critical level
- `send($message, $context = [], $level = 'info')` - Send with custom level

**Example with multiple rows:**

```php
Log::channel('telegram')
    ->buttons()
    ->url('View Order', 'https://example.com/orders/123')
    ->callback('Approve', 'order:approve:123')
    ->callback('Reject', 'order:reject:123')
    ->newRow()
    ->callback('Contact Customer', 'contact:customer:456')
    ->warning('Order #123 requires manual review');
```

#### Method 2: Using `addButton()` Macro (Quick Single Button)

Add a single URL button quickly:

```php
Log::channel('telegram')
    ->addButton('View Details', 'https://example.com/details')
    ->info('Payment received for order #123');
```

#### Method 3: Using `addButtons()` Macro (Multiple Buttons)

Add multiple buttons at once (each button goes on its own row):

```php
Log::channel('telegram')
    ->addButtons([
        ['text' => 'View', 'url' => 'https://example.com/view'],
        ['text' => 'Edit', 'callback_data' => 'edit:123'],
        ['text' => 'Delete', 'callback_data' => 'delete:123'],
    ])
    ->error('Critical error detected in order processing');
```

#### Using with Logger Instances

You can also use these macros with logger instances directly:

```php
use Iperamuna\TelegramLog\Logging\MacroableLogger;

$logger = new MacroableLogger('telegram');
$logger->pushHandler(new \Iperamuna\TelegramLog\Logging\TelegramButtonHandler());

$logger->buttons()
    ->callback('Retry', 'retry:task:456')
    ->error('Task execution failed');
```

#### Button Types

**URL Buttons:**
```php
->url('Open Website', 'https://example.com')
```

**Callback Buttons:**
```php
->callback('Approve', 'approve:123')
->callback('Reject', 'reject:123')
```

Callback buttons trigger webhook callbacks that can be handled by callback handlers (see Callbacks section below).

---

## Message Templates

This package supports customizable Blade templates for formatting log messages, inspired by [grkamil/laravel-telegram-logging](https://github.com/grkamil/laravel-telegram-logging).

### Default Templates

Two templates are included:

- **`telegram-log::standard`** (default) - Detailed format with environment, time, context, and extra data
- **`telegram-log::minimal`** - Simple format with just level and message

### Configure Template

Set the default template in `.env`:

```env
TELEGRAM_LOG_TEMPLATE=telegram-log::minimal
```

Or in `config/telegram-log.php`:

```php
'template' => env('TELEGRAM_LOG_TEMPLATE', 'telegram-log::standard'),
```

### Override Template Per Channel

You can override the template for a specific logging channel using `handler_with`:

```php
'channels' => [
    'telegram' => [
        'driver' => 'monolog',
        'handler' => \Iperamuna\TelegramLog\Logging\TelegramButtonHandler::class,
        'handler_with' => [
            'template' => 'telegram-log::minimal',
            // You can also override other parameters:
            // 'botToken' => env('TELEGRAM_CUSTOM_BOT_TOKEN'),
            // 'chatId' => env('TELEGRAM_CUSTOM_CHAT_ID'),
            // 'parseMode' => 'Markdown',
        ],
        'level' => env('LOG_LEVEL', 'debug'),
    ],
    
    'telegram-alerts' => [
        'driver' => 'monolog',
        'handler' => \Iperamuna\TelegramLog\Logging\TelegramButtonHandler::class,
        'handler_with' => [
            'template' => 'telegram-log::standard',
            'chatId' => env('TELEGRAM_ALERTS_CHAT_ID'),
        ],
        'level' => 'critical',
    ],
],
```

### Create Custom Templates

1. **Publish views** (optional, to customize):

```bash
php artisan vendor:publish --provider="Iperamuna\\TelegramLog\\TelegramLogServiceProvider" --tag="views"
```

This publishes templates to `resources/views/vendor/telegram-log/`.

2. **Create your custom template** in `resources/views/vendor/telegram-log/custom.blade.php`:

```blade
🚨 <b>{{ $level }}</b>

{{ $message }}

@if($context)
<pre>{{ json_encode($context, JSON_PRETTY_PRINT) }}</pre>
@endif
```

3. **Use your custom template**:

```php
// In config/telegram-log.php
'template' => 'telegram-log::custom',

// Or per channel
'handler_with' => [
    'template' => 'telegram-log::custom',
],
```

### Available Template Variables

Templates receive the following variables:

- `$level` - Log level name (e.g., "ERROR", "INFO")
- `$message` - Formatted log message
- `$context` - Context array (excluding buttons)
- `$extra` - Extra data array
- `$datetime` - Formatted datetime string (Y-m-d H:i:s)
- `$environment` - Application environment (from `config('app.env')`)

---

## Callbacks

### Callback Generator

Generate callback handler classes using Laravel Prompts:

```bash
php artisan telegram-log:make-callback BanUser --action=ban_user
```

- `name` is optional → if omitted, you'll be prompted:
  - **Callback class base name** (e.g. `BanUser`)
- `--action` is optional → if omitted, Prompts will:
  - Suggest `snake_case(name)` (e.g. `ban_user`)
  - Let you edit/confirm it

The command will:

1. Generate `app/Telegram/Callbacks/BanUserCallback.php`:

```php
class BanUserCallback extends AbstractTelegramCallbackHandler
{
    protected function handleParsed(array $update, string $action, string $payload): void
    {
        // Your logic here - $action and $payload are already parsed!
        // $action = "ban_user"
        // $payload = "5" (from "ban_user:5")
    }
}
```

2. Update `config/telegram-log-callbacks.php`:

```php
'map' => [
    'ban_user' => \App\Telegram\Callbacks\BanUserCallback::class,
],
```

3. Print usage hints:

```text
Use this callback_data in your buttons:
  ban_user:<payload>

Example:
  ->callback('Ban user', 'ban_user:5')
```

Because it uses **Laravel Prompts**:

- If you fully specify arguments & options, it runs non-interactive.
- If you omit them, you get a nice interactive flow.

### List Registered Callbacks

View all registered callback handlers:

```bash
php artisan telegram-log:list-callbacks
```

Outputs a table:

```text
+----------+---------------------------------------------+--------------+
| Action   | Handler                                     | Class exists |
+----------+---------------------------------------------+--------------+
| ban_user | App\Telegram\Callbacks\BanUserCallback   | yes          |
+----------+---------------------------------------------+--------------+
```

### Callback Registry & Facade

You can programmatically register callback handlers using the `TelegramCallbacks` facade:

```php
use Iperamuna\TelegramLog\Facades\TelegramCallbacks;

// Register a closure handler
TelegramCallbacks::on('approve', function (array $update, string $data) {
    // Handle the callback
    $parts = explode(':', $data, 2);
    $payload = $parts[1] ?? '';
    
    // Your logic here
});

// Register a class handler
TelegramCallbacks::on('ban_user', \App\Telegram\Callbacks\BanUserCallback::class);

// Get all registered handlers
$allHandlers = TelegramCallbacks::all();
```

**Note:** Handlers registered via the facade will override config-based handlers for the same action.

---

## Commands

### Interactive Setup

```bash
php artisan telegram-log:install
```

Interactive wizard that guides you through:
- Bot token setup and verification
- Chat ID detection (with webhook handling)
- Webhook configuration
- Health check setup
- Mode selection (instant/buffered)
- `.env` file updates

### Callback Management

```bash
# Generate a new callback handler
php artisan telegram-log:make-callback BanUser --action=ban_user

# List all registered callbacks
php artisan telegram-log:list-callbacks
```

### Webhook Management

```bash
# Set webhook (interactive)
php artisan telegram-log:set-webhook

# Set webhook with URL
php artisan telegram-log:set-webhook --url="https://your-app.com/telegram/callback"

# Delete webhook
php artisan telegram-log:set-webhook --delete
```

### Chat ID Discovery

```bash
# Discover chat IDs via getUpdates (interactive)
php artisan telegram-log:get-chat-id

# With options
php artisan telegram-log:get-chat-id --limit=20 --raw
```

### Buffered Log Flushing

```bash
# Flush buffered logs once
php artisan telegram-log:flush

# Flush in a loop (for daemon/worker)
php artisan telegram-log:flush --loop
```

---

## Health Check Route

Enable the health check endpoint in `config/telegram-log.php`:

```php
'health' => [
    'enabled' => env('TELEGRAM_LOG_HEALTH_ENABLED', false),
    'path'    => env('TELEGRAM_LOG_HEALTH_PATH', '/telegram/log/health'),
    'secret'  => env('TELEGRAM_LOG_HEALTH_SECRET', null),
],
```

When `enabled` is true, the package registers:

```text
GET /telegram/log/health
```

It returns JSON like:

```json
{
  "ok": true,
  "config": {
    "bot_token_set": true,
    "chat_id_set": true,
    "callback_enabled": true
  },
  "callbacks": {
    "configured": [
      { "action": "ban_user", "class": "App\\Telegram\\Callbacks\\BanUserCallback" }
    ],
    "missing_classes": []
  },
  "webhook": {
    "url": "https://your-app.com/telegram/callback",
    "pending_update_count": 0,
    "...": "..."
  }
}
```

If `TELEGRAM_LOG_HEALTH_SECRET` is set, the route expects:

```http
X-Telegram-Log-Health-Secret: <secret>
```

So you can put this behind a probe or internal monitoring.

---

## Tests

Dev dependencies:

- `pestphp/pest`
- `orchestra/testbench`

Run tests:

```bash
composer test
# or
./vendor/bin/pest
```

---

## License

MIT – see `LICENSE`.
