<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A single-required-argument command used to prove that tokens after a
 * bare "--" end-of-options terminator are bound as positional arguments
 * even when they look like options (C4), all the way through the real
 * Console::handle() pipeline.
 */
class EchoArgCommand extends Command
{
    public $signature = 'echoarg {value}';

    public $description = 'Captures its single bound argument.';

    public static $captured;

    public function handle()
    {
        static::$captured = $this->argument('value');
    }
}
