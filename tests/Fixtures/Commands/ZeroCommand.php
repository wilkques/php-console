<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * A command literally named "0". Console::handle() does
 * `$type = array_shift($commands); if (!$type) { ...show help...; exit; }`
 * which is a truthiness check, and the string "0" is falsy in PHP, so
 * today this command can never be dispatched (N19).
 */
class ZeroCommand extends Command
{
    public $signature = '0';

    public $description = 'A command literally named zero.';

    public static $dispatched = false;

    public function handle()
    {
        static::$dispatched = true;
    }
}
