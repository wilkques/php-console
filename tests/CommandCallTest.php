<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Exceptions\CommandNotFoundException;
use Wilkques\Console\Tests\Fixtures\Commands\CallerCommand;
use Wilkques\Console\Tests\Fixtures\Commands\CallerOfUnknownCommand;
use Wilkques\Console\Tests\Fixtures\Commands\CallerSilentCommand;
use Wilkques\Console\Tests\Fixtures\Commands\InnerCommand;

/**
 * Command::call()/callSilent() — dispatching one registered command from
 * inside another's handle(), the same thing Laravel's $this->call() does.
 */
class CommandCallTest extends TestCase
{
    protected function additionalSetUp()
    {
        CallerCommand::$exitCode = null;
        CallerSilentCommand::$exitCode = null;
        InnerCommand::$captured = null;
    }

    public function test_call_dispatches_the_target_command_and_returns_its_exit_code()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\CallerCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\InnerCommand');

        ob_start();
        $console->handle(array('caller'));
        ob_get_clean();

        $this->assertSame(7, CallerCommand::$exitCode, "call() must return the target command's own exit code");
    }

    public function test_call_threads_arguments_and_options_through_to_the_target_command()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\CallerCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\InnerCommand');

        ob_start();
        $console->handle(array('caller'));
        ob_get_clean();

        $this->assertSame(array('name' => 'Alice', 'shout' => true), InnerCommand::$captured);
    }

    public function test_call_writes_the_target_commands_output_normally()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\CallerCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\InnerCommand');

        ob_start();
        $console->handle(array('caller'));
        $output = ob_get_clean();

        $this->assertStringContainsStringCompat('inner ran with Alice', $output);
    }

    public function test_call_silent_suppresses_the_target_commands_output_but_keeps_its_exit_code()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\CallerSilentCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\InnerCommand');

        ob_start();
        $console->handle(array('caller-silent'));
        $output = ob_get_clean();

        $this->assertSame(7, CallerSilentCommand::$exitCode);
        $this->assertStringContainsStringCompat('before', $output);
        $this->assertStringContainsStringCompat('after', $output);
        $this->assertFalse(
            strpos($output, 'inner ran with Bob') !== false,
            'callSilent() must not let the target command\'s own output through'
        );
    }

    public function test_calling_an_unregistered_command_directly_throws_command_not_found()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\CommandNotFoundException');

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\InnerCommand');

        $console->call('nope:such:command');
    }

    public function test_a_call_to_an_unregistered_command_nested_inside_a_dispatched_command_still_exits_cleanly()
    {
        // The failure happens inside CallerOfUnknownCommand::handle(), so
        // Console::execute()'s own safety net (N4/N19-era: a command that
        // throws must not crash the whole process) catches it same as any
        // other exception a handle() lets escape, and converts it to a
        // non-zero exit code instead of a raw fatal.
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\CallerOfUnknownCommand');

        ob_start();
        $exitCode = $console->handle(array('caller-of-unknown'));
        ob_get_clean();

        $this->assertNotSame(0, $exitCode);
    }

    public function test_call_on_a_command_never_dispatched_through_a_console_throws()
    {
        $this->expectExceptionCompat('RuntimeException');

        $command = new InnerCommand();

        $command->call('inner');
    }
}
