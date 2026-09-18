<?php

namespace Wilkques\Console\Tests\Fixtures\MixedConsoleCommands;

use Wilkques\Console\Command;

/**
 * An abstract base class that happens to live in the command directory
 * (a common real-world layout: a shared "BaseCommand" alongside concrete
 * commands). It is not instantiable, so today Console::boot() fatals
 * trying to `new` it (N13).
 */
abstract class BaseAbstractCommand extends Command
{
}
