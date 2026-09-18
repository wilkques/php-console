<?php

namespace Wilkques\Console\Exceptions;

/**
 * Base exception for every error raised by the console framework.
 *
 * All exceptions thrown by this package extend this class so that
 * calling code (e.g. a top-level dispatcher) can catch a single type
 * to handle any console-related failure, and can call
 * {@see ConsoleException::getExitCode()} to determine the process
 * exit status without needing an `instanceof` chain.
 */
class ConsoleException extends \RuntimeException
{
    /**
     * The process exit code that should be used when this exception
     * terminates the console run. Defaults to 1 (generic failure).
     *
     * @var int
     */
    protected $exitCode = 1;

    /**
     * Get the process exit code associated with this exception.
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }
}
