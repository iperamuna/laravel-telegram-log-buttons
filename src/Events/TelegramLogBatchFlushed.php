<?php

namespace Iperamuna\TelegramLog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramLogBatchFlushed
{
    use Dispatchable, SerializesModels;

    /**
     * @var array<int, array<string,mixed>>
     */
    public array $entries;

    public string $body;

    public int $count;

    public string|int $chatId;

    public ?string $parseMode;

    public function __construct(
        array $entries,
        string $body,
        int $count,
        string|int $chatId,
        ?string $parseMode = null,
    ) {
        $this->entries = $entries;
        $this->body = $body;
        $this->count = $count;
        $this->chatId = $chatId;
        $this->parseMode = $parseMode;
    }
}
