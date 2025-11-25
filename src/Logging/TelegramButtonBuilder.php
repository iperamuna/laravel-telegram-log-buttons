<?php

namespace Iperamuna\TelegramLog\Logging;

use Monolog\Logger;

class TelegramButtonBuilder
{
    protected Logger $logger;

    /**
     * @var array<int, array<int, array<string,string>>>
     */
    protected array $inlineKeyboard = [];

    /**
     * @var array<string,mixed>
     */
    protected array $extraContext = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function newRow(): self
    {
        $this->inlineKeyboard[] = [];

        return $this;
    }

    protected function ensureRow(): void
    {
        if (empty($this->inlineKeyboard)) {
            $this->inlineKeyboard[] = [];
        }
    }

    public function url(string $text, string $url): self
    {
        $this->ensureRow();

        $this->inlineKeyboard[count($this->inlineKeyboard) - 1][] = [
            'text' => $text,
            'url' => $url,
        ];

        return $this;
    }

    public function callback(string $text, string $callbackData): self
    {
        $this->ensureRow();

        $this->inlineKeyboard[count($this->inlineKeyboard) - 1][] = [
            'text' => $text,
            'callback_data' => $callbackData,
        ];

        return $this;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->extraContext = array_merge($this->extraContext, $context);

        return $this;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function send(string $message, array $context = [], string $level = 'info')
    {
        $context = array_merge_recursive(
            ['buttons' => $this->inlineKeyboard],
            $this->extraContext,
            $context,
        );

        return $this->logger->{$level}($message, $context);
    }

    public function info(string $message, array $context = [])
    {
        return $this->send($message, $context, 'info');
    }

    public function warning(string $message, array $context = [])
    {
        return $this->send($message, $context, 'warning');
    }

    public function error(string $message, array $context = [])
    {
        return $this->send($message, $context, 'error');
    }

    public function critical(string $message, array $context = [])
    {
        return $this->send($message, $context, 'critical');
    }
}
