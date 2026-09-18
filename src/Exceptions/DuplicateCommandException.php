<?php

namespace Wilkques\Console\Exceptions;

/**
 * Thrown when two different command classes declare the same
 * command name via their `$signature` property.
 *
 * Example:
 *
 *     class FooCommand { protected $signature = 'dup:same'; }
 *     class BarCommand { protected $signature = 'dup:same'; }
 *
 * Registering both `FooCommand` and `BarCommand` with the console
 * application triggers this exception because "dup:same" would be
 * ambiguous to dispatch.
 */
class DuplicateCommandException extends ConsoleException
{
}
