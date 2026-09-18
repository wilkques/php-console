<?php

namespace Wilkques\Console\Exceptions;

/**
 * Thrown when the arguments or options supplied on the command line
 * do not match what a command's signature declares.
 *
 * This includes:
 *  - too many positional arguments
 *  - too few required positional arguments
 *  - an option that was not declared in the signature
 *
 * Example:
 *
 *     php artisan member:get foo bar
 *
 * If the command's signature only declares `{username}` (a single
 * required argument), passing both "foo" and "bar" triggers this
 * exception.
 *
 * NOTE: This class intentionally shares its short name with the
 * global SPL `\InvalidArgumentException`. Within the
 * `Wilkques\Console\Exceptions` namespace, an unqualified reference
 * to `InvalidArgumentException` resolves to *this* class, not the
 * SPL one. This is deliberate so that all console-related argument
 * errors can be caught via {@see ConsoleException}; use the fully
 * qualified `\InvalidArgumentException` if the global SPL exception
 * is what you actually mean.
 */
class InvalidArgumentException extends ConsoleException
{
}
