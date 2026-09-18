<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Exceptions\InvalidArgumentException;
use Wilkques\Console\Tests\Fixtures\Commands\OptionsCommand;
use Wilkques\Console\Tests\Fixtures\SignatureCommand;

/**
 * {--flag}, {--name=default}, {--name=}, and CLI-level "--name=value" /
 * "--flag" option binding, both at the signature-parsing level
 * (Command::toArray()) and through the full Console::handle() pipeline.
 */
class OptionBindingTest extends TestCase
{
    /**
     * N1 (CRITICAL) — Parser::parser() is reused for both CLI tokens and
     * signature tokens, but the semantics are opposite: on the CLI,
     * "--force" present means true. In a *signature*, "{--force}" is only
     * a declaration of an accepted flag and must default to false, not
     * true, until the caller actually passes it.
     */
    public function test_valueless_signature_flag_defaults_to_false()
    {
        $command = new SignatureCommand('cmd {--verbose : Be noisy}');

        $signature = $command->toArray();
        $this->assertSame(false, $signature['options']['verbose']);
    }

    public function test_signature_flag_explicit_default_false_stays_false()
    {
        $command = new SignatureCommand('cmd {--force=false : desc}');

        $signature = $command->toArray();
        $this->assertSame(false, $signature['options']['force']);
    }

    public function test_signature_flag_explicit_default_true_stays_true()
    {
        $command = new SignatureCommand('cmd {--force=true : desc}');

        $signature = $command->toArray();
        $this->assertSame(true, $signature['options']['force']);
    }

    public function test_signature_option_with_named_default_value()
    {
        $command = new SignatureCommand('cmd {--name=guest : desc}');

        $signature = $command->toArray();
        $this->assertSame('guest', $signature['options']['name']);
    }

    /**
     * N26 (LOW) — "{--name=}" (an option that accepts a value, with no
     * default given) must yield null, matching Laravel's semantics, not
     * an empty string.
     */
    public function test_signature_option_with_empty_default_yields_null()
    {
        $command = new SignatureCommand('cmd {--name=}');

        $signature = $command->toArray();
        $this->assertNull($signature['options']['name']);
    }

    public function test_full_pipeline_uses_signature_defaults_when_nothing_passed_on_cli()
    {
        OptionsCommand::$captured = null;

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\OptionsCommand');

        $console->handle(array('opts:test'));

        $this->assertSame(
            array('flag' => false, 'name' => null, 'verbose' => false),
            OptionsCommand::$captured
        );
    }

    public function test_full_pipeline_cli_options_override_signature_defaults()
    {
        OptionsCommand::$captured = null;

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\OptionsCommand');

        $console->handle(array('opts:test', '--flag', '--name=bob'));

        $this->assertSame(
            array('flag' => true, 'name' => 'bob', 'verbose' => false),
            OptionsCommand::$captured
        );
    }

    /**
     * C5 — an option the signature never declared must be rejected.
     *
     * Console::handle() catches this ConsoleException internally and
     * returns exit code 1 instead of letting it propagate, so the
     * exception itself is asserted against the protected execute()
     * method (the real dispatch logic); handle()'s exit-code contract is
     * asserted separately, for the exact same input.
     */
    public function test_undeclared_option_raises_invalid_argument_exception()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\InvalidArgumentException');

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\OptionsCommand');

        $this->invoke($console, 'execute', array(array('opts:test', '--totally-unknown=xyz')));
    }

    public function test_undeclared_option_yields_exit_code_one_via_handle()
    {
        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\OptionsCommand');

        $exitCode = $console->handle(array('opts:test', '--totally-unknown=xyz'));

        $this->assertSame(1, $exitCode);
    }
}
