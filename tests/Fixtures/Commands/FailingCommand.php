<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A command whose handle() returns a non-zero int, used to assert
 * Console::handle() propagates that value as the process exit code (C1).
 */
class FailingCommand extends Command
{
    public $signature = 'fail';

    public $description = 'Always fails.';

    public function handle()
    {
        return 1;
    }
}
