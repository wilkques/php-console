<?php

namespace Wilkques\Console\Tests\Fixtures\DuplicateConsoleCommands;

use Wilkques\Console\Command;

/**
 * Declares the same command name ("dup:same") as FooCommand in this same
 * directory, on purpose, to exercise duplicate-name detection (C3).
 */
class BarCommand extends Command
{
    public $signature = 'dup:same';

    public $description = 'Second of a pair of colliding command names.';

    public function handle()
    {
        return 0;
    }
}
