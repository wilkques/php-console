<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * Same as CallerCommand, but via callSilent() — used to prove "inner"'s
 * own line() output never reaches the outer command's stdout.
 */
class CallerSilentCommand extends Command
{
    public $signature = 'caller-silent';

    public $description = 'Calls "inner" via callSilent().';

    public static $exitCode;

    public function handle()
    {
        $this->line('before');

        static::$exitCode = $this->callSilent('inner', array('Bob'));

        $this->line('after');

        return 0;
    }
}
