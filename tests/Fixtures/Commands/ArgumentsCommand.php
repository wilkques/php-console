<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * Exercises the full required / optional / default / array argument
 * pipeline through Console::handle() -> handleArguments().
 */
class ArgumentsCommand extends Command
{
    public $signature = 'args:test {first} {second?} {third=fallback} {rest*}';

    public $description = 'Captures its bound arguments for inspection.';

    public static $captured;

    public function handle()
    {
        static::$captured = $this->arguments();
    }
}
