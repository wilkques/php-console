<?php

namespace Wilkques\Console\Tests\Fixtures\MixedConsoleCommands;

/**
 * A perfectly ordinary, instantiable PHP class that does NOT extend
 * Wilkques\Console\Command and does NOT implement Commandable. Today,
 * Console::boot() scans every ".php" file under the command root with no
 * class_exists()/is_subclass_of() guard and instantiates it unconditionally,
 * so a file like this one fatals the whole discovery pass (N13).
 */
class NotACommand
{
    public function notAHandleMethod()
    {
        return 'not a command';
    }
}
