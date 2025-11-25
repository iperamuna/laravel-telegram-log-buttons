<?php

namespace Iperamuna\TelegramLog\Tests\Feature;

use Iperamuna\TelegramLog\Logging\MacroableLogger;
use Iperamuna\TelegramLog\Logging\TelegramButtonBuilder;
use Iperamuna\TelegramLog\TelegramLogServiceProvider;
use Orchestra\Testbench\TestCase;

class ButtonBuilderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            TelegramLogServiceProvider::class,
        ];
    }

    public function test_buttons_macro_is_registered(): void
    {
        $logger = new MacroableLogger('test');

        $builder = $logger->buttons();

        $this->assertInstanceOf(TelegramButtonBuilder::class, $builder);
    }
}
