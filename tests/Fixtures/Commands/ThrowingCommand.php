<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A command whose handle() throws, used to assert Console::handle()
 * catches it rather than letting it escape as a raw fatal, and returns
 * a non-zero exit code (C2).
 */
class ThrowingCommand extends Command
{
    public $signature = 'throw:boom';

    public $description = 'Always throws.';

    public function handle()
    {
        throw new \RuntimeException('boom');
    }
}
