<?php

namespace Wilkques\Console\Exceptions;

/**
 * Thrown when a requested command name has not been registered with
 * the console application.
 *
 * Example:
 *
 *     php artisan nope:such
 *
 * If no command declares the signature name "nope:such", this
 * exception is thrown instead of attempting to dispatch it.
 */
class CommandNotFoundException extends ConsoleException
{
}
