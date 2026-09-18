<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * Calls a command name that was never registered — used to prove that,
 * unlike a target command's own handle() failing, an unresolvable call()
 * target throws instead of quietly becoming a non-zero exit code.
 */
class CallerOfUnknownCommand extends Command
{
    public $signature = 'caller-of-unknown';

    public $description = 'Calls a command that does not exist.';

    public function handle()
    {
        $this->call('nope:such:command');

        return 0;
    }
}
