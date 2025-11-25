<?php

namespace Iperamuna\TelegramLog\Tests\Feature;

use Iperamuna\TelegramLog\Logging\AbstractTelegramCallbackHandler;
use Iperamuna\TelegramLog\Support\TelegramCallbackRegistry;
use Iperamuna\TelegramLog\TelegramLogServiceProvider;
use Orchestra\Testbench\TestCase;

class CallbackRegistryTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            TelegramLogServiceProvider::class,
        ];
    }

    public function test_registry_resolves_closure(): void
    {
        $registry = new TelegramCallbackRegistry;
        $called = false;

        $registry->on('test', function () use (&$called) {
            $called = true;
        });

        $handler = $registry->resolve('test');
        $this->assertIsCallable($handler);

        $handler([], 'test:payload');
        $this->assertTrue($called);
    }

    public function test_registry_resolves_abstract_callback_handler(): void
    {
        $registry = new TelegramCallbackRegistry;

        $handlerClass = new class extends AbstractTelegramCallbackHandler
        {
            public ?array $update = null;

            public ?string $action = null;

            public ?string $payload = null;

            protected function handleParsed(array $update, string $action, string $payload): void
            {
                $this->update = $update;
                $this->action = $action;
                $this->payload = $payload;
            }
        };

        $handlerClassName = get_class($handlerClass);
        $this->app->singleton($handlerClassName, fn () => $handlerClass);

        $registry->on('test_action', $handlerClassName);

        $resolved = $registry->resolve('test_action');
        $this->assertIsCallable($resolved);

        $testUpdate = ['callback_query' => ['data' => 'test_action:123']];
        $resolved($testUpdate, 'test_action:123');

        $this->assertEquals($testUpdate, $handlerClass->update);
        $this->assertEquals('test_action', $handlerClass->action);
        $this->assertEquals('123', $handlerClass->payload);
    }

    public function test_abstract_callback_handler_parses_action_and_payload(): void
    {
        $handler = new class extends AbstractTelegramCallbackHandler
        {
            public ?array $update = null;

            public ?string $action = null;

            public ?string $payload = null;

            protected function handleParsed(array $update, string $action, string $payload): void
            {
                $this->update = $update;
                $this->action = $action;
                $this->payload = $payload;
            }
        };

        $testUpdate = ['test' => 'data'];
        $handler->handle($testUpdate, 'ban_user:456');

        $this->assertEquals($testUpdate, $handler->update);
        $this->assertEquals('ban_user', $handler->action);
        $this->assertEquals('456', $handler->payload);
    }

    public function test_abstract_callback_handler_handles_missing_payload(): void
    {
        $handler = new class extends AbstractTelegramCallbackHandler
        {
            public ?string $action = null;

            public ?string $payload = null;

            protected function handleParsed(array $update, string $action, string $payload): void
            {
                $this->action = $action;
                $this->payload = $payload;
            }
        };

        $handler->handle([], 'action_only');

        $this->assertEquals('action_only', $handler->action);
        $this->assertEquals('', $handler->payload);
    }
}
