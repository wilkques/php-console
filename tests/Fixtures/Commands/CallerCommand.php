<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * Calls "inner" via Command::call() and remembers what came back, so a
 * test can assert on it without needing to inspect stdout itself.
 */
class CallerCommand extends Command
{
    public $signature = 'caller';

    public $description = 'Calls "inner" via call().';

    public static $exitCode;

    public function handle()
    {
        static::$exitCode = $this->call('inner', array('Alice', '--shout'));

        return 0;
    }
}
