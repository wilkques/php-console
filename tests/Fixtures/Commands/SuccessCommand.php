<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A command whose handle() returns nothing (implicit null), used to
 * assert Console::handle() treats "no return value" as a successful
 * (0) exit code (C1).
 */
class SuccessCommand extends Command
{
    public $signature = 'success {name=World}';

    public $description = 'Always succeeds.';

    public static $captured;

    public function handle()
    {
        static::$captured = $this->arguments();
    }
}
