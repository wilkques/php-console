<?php

namespace Wilkques\Console\Tests\Fixtures\MixedConsoleCommands;

use Wilkques\Console\Command;

class PingCommand extends Command
{
    public $signature = 'ping';

    public $description = 'The one real command in a directory that also contains non-command PHP files.';

    public function handle()
    {
        return 0;
    }
}
