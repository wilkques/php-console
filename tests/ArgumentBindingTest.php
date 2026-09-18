<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Exceptions\InvalidArgumentException;
use Wilkques\Console\Exceptions\InvalidSignatureException;
use Wilkques\Console\Tests\Fixtures\Commands\ArgumentsCommand;
use Wilkques\Console\Tests\Fixtures\Commands\EchoArgCommand;
use Wilkques\Console\Tests\Fixtures\SignatureCommand;

/**
 * {arg} required, {arg?} optional, {arg=default} default, {arg*} array —
 * both the low-level Command::getArgumentDefinitions() parsing and the
 * full Console::handle() -> handleArguments() binding pipeline.
 */
class ArgumentBindingTest extends TestCase
{
    public function test_required_optional_default_and_array_tokens_parse_correctly()
    {
        $command = new SignatureCommand('cmd {first} {second?} {third=fallback} {rest*}');

        $definitions = $command->getArgumentDefinitions();

        $this->assertSame(
            array('name' => 'first', 'optional' => false, 'default' => null, 'array' => false),
            $definitions[0]
        );
        $this->assertSame(
            array('name' => 'second', 'optional' => true, 'default' => null, 'array' => false),
            $definitions[1]
        );
        $this->assertSame(
            array('name' => 'third', 'optional' => true, 'default' => 'fallback', 'array' => false),
            $definitions[2]
        );
        $this->assertSame(
            array('name' => 'rest', 'optional' => true, 'default' => null, 'array' => true),
            $definitions[3]
        );
    }

    public function test_full_pipeline_binds_required_optional_default_and_array_arguments()
    {
        ArgumentsCommand::$captured = null;

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\ArgumentsCommand');

        $console->handle(array('args:test', 'one', 'two', 'three', 'four', 'five'));

        $this->assertSame(array(
            'first' => 'one',
            'second' => 'two',
            'third' => 'three',
            'rest' => array('four', 'five'),
        ), ArgumentsCommand::$captured);
    }

    public function test_full_pipeline_applies_optional_and_default_when_omitted()
    {
        ArgumentsCommand::$captured = null;

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\ArgumentsCommand');

        $console->handle(array('args:test', 'one'));

        $this->assertSame(array(
            'first' => 'one',
            'second' => null,
            'third' => 'fallback',
            'rest' => array(),
        ), ArgumentsCommand::$captured);
    }

    /**
     * Console::handle() is the public entry point: it catches every
     * ConsoleException thrown while resolving/validating a command and
     * turns it into a clean stderr message plus exit code 1, so it never
     * lets the exception itself propagate. The real dispatch logic that
     * throws is execute() (protected); assert the exception there, and
     * separately assert handle()'s exit-code contract for the exact same
     * input.
     *
     * Uses EchoArgCommand (a single fixed required argument, no trailing
     * "{arg*}") rather than ArgumentsCommand: a trailing array argument
     * absorbs any number of extra tokens by design, so it can never
     * trigger the "too many arguments" path.
     */
    public function test_too_many_arguments_raises_invalid_argument_exception()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\InvalidArgumentException');

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\EchoArgCommand');

        $this->invoke($console, 'execute', array(array('echoarg', 'one', 'two')));
    }

    public function test_too_many_arguments_yields_exit_code_one_via_handle()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\EchoArgCommand');

        $exitCode = $console->handle(array('echoarg', 'one', 'two'));

        $this->assertSame(1, $exitCode);
    }

    public function test_too_few_arguments_raises_invalid_argument_exception()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\InvalidArgumentException');

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\ArgumentsCommand');

        // "first" is required and nothing is supplied.
        $this->invoke($console, 'execute', array(array('args:test')));
    }

    public function test_too_few_arguments_yields_exit_code_one_via_handle()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\ArgumentsCommand');

        // "first" is required and nothing is supplied.
        $exitCode = $console->handle(array('args:test'));

        $this->assertSame(1, $exitCode);
    }

    /**
     * N10 — handleArguments() only special-cases the *last* definition
     * being an array ("{arg*}"). If a "*" argument is declared anywhere
     * else, its greedy consumption silently nulls out a later required
     * argument instead of raising a diagnostic. Declaring a non-final
     * array argument must be rejected up front.
     */
    public function test_non_final_array_argument_raises_invalid_signature_exception()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\InvalidSignatureException');

        $command = new SignatureCommand('sync {files*} {target}');

        $command->getArgumentDefinitions();
    }
}
