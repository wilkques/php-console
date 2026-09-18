<?php

namespace Wilkques\Console\Tests\Fixtures\ConsoleCommands;

use Wilkques\Console\Command;

class FarewellCommand extends Command
{
    public $signature = 'farewell {name?}';

    public $description = 'Says goodbye.';

    public function handle()
    {
        return 0;
    }
}
