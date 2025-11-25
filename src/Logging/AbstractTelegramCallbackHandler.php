<?php

namespace Iperamuna\TelegramLog\Logging;

use Iperamuna\TelegramLog\Contracts\TelegramCallbackHandler;

abstract class AbstractTelegramCallbackHandler implements TelegramCallbackHandler
{
    /**
     * Handle a Telegram callback_query update.
     *
     * This method parses the callback_data and calls handleParsed with the action and payload.
     * Override this method if you need custom parsing logic.
     *
     * @param  array<string,mixed>  $update  Full Telegram update payload
     * @param  string  $data  Raw callback_data string (format: "action:payload")
     */
    public function handle(array $update, string $data): void
    {
        $parts = explode(':', $data, 2);
        $action = $parts[0] ?? $data;
        $payload = $parts[1] ?? '';

        $this->handleParsed($update, $action, $payload);
    }

    /**
     * Handle a parsed Telegram callback_query update.
     *
     * @param  array<string,mixed>  $update  Full Telegram update payload
     * @param  string  $action  The action name (extracted from callback_data)
     * @param  string  $payload  The payload after the colon (extracted from callback_data)
     */
    abstract protected function handleParsed(array $update, string $action, string $payload): void;
}
