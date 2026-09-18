<?php

namespace Wilkques\Console\Tests\Fixtures\Commands;

use Wilkques\Console\Command;

/**
 * Exercises the option-binding pipeline through Console::handle() ->
 * handleOptions(). Declares a valueless flag, a valueless-default
 * option, and a described option so both signature-level and CLI-level
 * option semantics can be asserted end to end.
 */
class OptionsCommand extends Command
{
    public $signature = 'opts:test {--flag} {--name=} {--verbose : Be noisy}';

    public $description = 'Captures its bound options for inspection.';

    public static $captured;

    public function handle()
    {
        static::$captured = $this->options();
    }
}
