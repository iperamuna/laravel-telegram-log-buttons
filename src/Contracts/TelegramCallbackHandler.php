<?php

namespace Iperamuna\TelegramLog\Contracts;

interface TelegramCallbackHandler
{
    /**
     * Handle a Telegram callback_query update.
     *
     * @param  array<string,mixed>  $update  Full Telegram update payload
     * @param  string  $data  Raw callback_data string (format: "action:payload")
     *
     * @note For convenience, extend AbstractTelegramCallbackHandler instead of
     *       implementing this interface directly. The abstract class will parse
     *       the callback_data and call handleParsed($update, $action, $payload).
     */
    public function handle(array $update, string $data): void;
}
