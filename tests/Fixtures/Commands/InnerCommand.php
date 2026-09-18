<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A plain command meant to be invoked via Command::call()/callSilent()
 * rather than dispatched directly — captures what it was given and
 * writes a line, so callers can assert both the exit code AND that
 * arguments/options actually made it through.
 */
class InnerCommand extends Command
{
    public $signature = 'inner {name=World} {--shout}';

    public $description = 'Invoked via call()/callSilent() in tests.';

    public static $captured;

    public function handle()
    {
        static::$captured = array(
            'name' => $this->argument('name'),
            'shout' => $this->option('shout'),
        );

        $this->line('inner ran with ' . $this->argument('name'));

        return 7;
    }
}
