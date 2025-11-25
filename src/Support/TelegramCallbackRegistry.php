<?php

namespace Iperamuna\TelegramLog\Support;

use Closure;
use Iperamuna\TelegramLog\Contracts\TelegramCallbackHandler;

class TelegramCallbackRegistry
{
    /**
     * @var array<string, Closure|string>
     */
    protected array $handlers = [];

    /**
     * Register a callback handler for an action.
     *
     * @param  string  $action  The action name (e.g. "ban_user")
     * @param  Closure|string  $handler  Either a Closure or a class name that implements TelegramCallbackHandler
     *                                   For convenience, use AbstractTelegramCallbackHandler as base class
     */
    public function on(string $action, Closure|string $handler): self
    {
        $this->handlers[$action] = $handler;

        return $this;
    }

    public function resolve(string $action): ?callable
    {
        if (! isset($this->handlers[$action])) {
            return null;
        }

        $handler = $this->handlers[$action];

        if ($handler instanceof Closure) {
            return $handler;
        }

        if (is_string($handler)) {
            $instance = app($handler);

            if ($instance instanceof TelegramCallbackHandler) {
                return [$instance, 'handle'];
            }
        }

        return null;
    }

    /**
     * @return array<string, Closure|string>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
