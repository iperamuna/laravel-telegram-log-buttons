<?php

namespace Iperamuna\TelegramLog\Logging;

use Illuminate\Support\Traits\Macroable;
use Monolog\Logger as MonologLogger;

class MacroableLogger extends MonologLogger
{
    use Macroable;

    /**
     * Create a new logger instance.
     */
    public function __construct(string $name, array $handlers = [], array $processors = [], ?\DateTimeZone $timezone = null)
    {
        parent::__construct($name, $handlers, $processors, $timezone);
    }
}
