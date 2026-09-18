<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Tests\Fixtures\Commands\FailingCommand;
use Wilkques\Console\Tests\Fixtures\Commands\SuccessCommand;
use Wilkques\Console\Tests\Fixtures\Commands\ThrowingCommand;
use Wilkques\Console\Tests\Fixtures\Commands\ZeroCommand;

/**
 * C1 — Console::handle() must RETURN an int exit code: a command whose
 * handle() returns nothing means success (0); a command whose handle()
 * returns a non-zero int propagates that value.
 *
 * C2 — an exception thrown inside a command's handle() must be caught by
 * Console::handle() (not escape as a raw fatal) and must also result in
 * a non-zero exit code.
 */
class ExitCodeTest extends TestCase
{
    public function test_command_returning_nothing_yields_exit_code_zero()
    {
        $console = $this->makeConsole();
        $console->register(SuccessCommand::class);

        $exitCode = $console->handle(['success']);

        $this->assertSame(0, $exitCode);
    }

    public function test_command_returning_one_propagates_as_exit_code()
    {
        $console = $this->makeConsole();
        $console->register(FailingCommand::class);

        $exitCode = $console->handle(['fail']);

        $this->assertSame(1, $exitCode);
    }

    public function test_exception_thrown_in_handle_is_caught_and_yields_non_zero_exit_code()
    {
        $console = $this->makeConsole();
        $console->register(ThrowingCommand::class);

        $exitCode = $console->handle(['throw:boom']);

        $this->assertIsInt($exitCode);
        $this->assertNotSame(0, $exitCode);
    }

    /**
     * N19 — Console::handle() does `$type = array_shift($commands); if
     * (!$type) { ...show help...; exit; }`, a truthiness check. The
     * string "0" is falsy in PHP, so a command literally named "0" can
     * never be dispatched today; it always falls into the "no command
     * given" help branch instead.
     *
     * Isolated in its own process: today that help branch ends with a
     * bare exit;, which would otherwise kill the whole PHPUnit run.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_command_literally_named_zero_still_dispatches()
    {
        ZeroCommand::$dispatched = false;

        $console = $this->makeConsole();
        $console->register(ZeroCommand::class);

        $console->handle(['0']);

        $this->assertTrue(ZeroCommand::$dispatched);
    }
}
