<?php

namespace Wilkques\Console\Tests\Fixtures\ConsoleCommands;

use Wilkques\Console\Command;

class GreetCommand extends Command
{
    public $signature = 'greet {name=World}';

    public $description = 'Greets someone.';

    public function handle()
    {
        return 0;
    }
}
