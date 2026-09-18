<?php

namespace Wilkques\Console\Exceptions;

/**
 * Thrown when a command's `$signature` property is empty or
 * otherwise cannot be parsed into a command name / arguments /
 * options structure.
 *
 * Example:
 *
 *     class FooCommand { protected $signature = ''; }
 *
 * An empty (or malformed) signature string cannot be parsed to
 * determine the command name, so this exception is thrown instead
 * of registering the command.
 */
class InvalidSignatureException extends ConsoleException
{
}
