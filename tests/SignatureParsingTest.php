<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Exceptions\InvalidSignatureException;
use Wilkques\Console\Tests\Fixtures\SignatureCommand;

/**
 * Command::regexMatches() / getHelper() / toArray() turn a $signature
 * string into a command name plus structured argument/option tokens.
 */
class SignatureParsingTest extends TestCase
{
    /**
     * C6 — leading whitespace/newline before the command name breaks the
     * "^([^\s]+)" regex (no /m flag), so the command name is never
     * captured and the *first argument token* gets shifted off instead
     * and lost.
     */
    public function test_leading_whitespace_and_newline_does_not_break_command_name()
    {
        $command = new SignatureCommand("\n    deploy {env}");

        $helper = $command->getHelper();

        $this->assertSame('deploy', $helper['command']);
        $this->assertSame(['env'], $command->toArray()['arguments']);
    }

    /**
     * C7 — an empty signature must raise a clear exception rather than
     * silently producing a null/empty command name.
     */
    public function test_empty_signature_raises_invalid_signature_exception()
    {
        $this->expectException(InvalidSignatureException::class);

        (new SignatureCommand(''))->getHelper();
    }

    /**
     * N7 — the signature regex uses ":" as the description separator
     * with no escaping, so default values containing a colon (DSNs,
     * timestamps, URLs) are truncated at the first colon. Only the
     * " : "-shaped separator between the token and its description
     * should end the token.
     */
    public function test_default_value_containing_colon_is_not_truncated_dsn()
    {
        $command = new SignatureCommand('cmd {--dsn=mysql:host=localhost : DSN}');

        $this->assertSame('mysql:host=localhost', $command->toArray()['options']['dsn']);
    }

    public function test_default_value_containing_colon_is_not_truncated_time()
    {
        $command = new SignatureCommand('cmd {--time=10:30 : time}');

        $this->assertSame('10:30', $command->toArray()['options']['time']);
    }

    public function test_default_value_containing_colon_is_not_truncated_url()
    {
        $command = new SignatureCommand('cmd {--url=https://x : endpoint}');

        $this->assertSame('https://x', $command->toArray()['options']['url']);
    }

    /**
     * N9 — the regex's third alternative ("--opt : desc" outside of
     * braces) is dead code nothing reads, but it still contributes an
     * empty string into the same capture group used for {...} tokens.
     * After array_shift() in toArray(), that empty string survives as a
     * required argument with an empty name, and the command can never
     * be satisfied. The fix must make this signature either parse
     * sanely (no empty-named argument) or refuse to register at all.
     */
    public function test_bare_double_dash_line_does_not_produce_an_empty_named_argument()
    {
        $command = new SignatureCommand("do:something\n--force : force it");

        try {
            $definitions = $command->getArgumentDefinitions();
        } catch (InvalidSignatureException $e) {
            $this->addToAssertionCount(1);

            return;
        }

        // The dangling "--force : force it" line lives outside of any
        // "{...}" braces, so it never becomes a token at all: this
        // signature declares zero arguments, not one with an empty name.
        $this->assertSame([], $definitions);

        foreach ($definitions as $definition) {
            $this->assertNotSame(
                '',
                $definition['name'],
                'A signature must never produce an argument with an empty name.'
            );
        }
    }

    /**
     * REGRESSION 1 — the token/description separator must be "a colon
     * followed by whitespace (or end of token)", not "first colon" (that
     * truncates colon-containing defaults) and not "first ' : '" (that
     * requires whitespace on both sides and breaks the very common
     * "{name: description}" style with no leading space).
     */
    public function test_colon_immediately_after_argument_name_splits_on_the_colon()
    {
        $command = new SignatureCommand('cmd {username: 帳號}');

        $this->assertSame(['username'], $command->toArray()['arguments']);
    }

    public function test_colon_with_space_before_and_after_argument_name_splits_on_the_colon()
    {
        $command = new SignatureCommand('cmd {username : 帳號}');

        $this->assertSame(['username'], $command->toArray()['arguments']);
    }

    public function test_argument_with_no_colon_is_used_as_is()
    {
        $command = new SignatureCommand('cmd {username}');

        $this->assertSame(['username'], $command->toArray()['arguments']);
    }

    public function test_colon_immediately_after_option_name_splits_on_the_colon()
    {
        $command = new SignatureCommand('cmd {--debug: 說明}');

        $this->assertArrayHasKey('debug', $command->toArray()['options']);
    }

    public function test_colon_with_space_before_and_after_option_name_splits_on_the_colon()
    {
        $command = new SignatureCommand('cmd {--debug : 說明}');

        $this->assertArrayHasKey('debug', $command->toArray()['options']);
    }

    public function test_dsn_default_with_colon_not_followed_by_whitespace_is_kept_whole()
    {
        $command = new SignatureCommand('cmd {--dsn=mysql:host=localhost : DSN}');

        $this->assertSame('mysql:host=localhost', $command->toArray()['options']['dsn']);
    }

    public function test_time_default_with_colon_not_followed_by_whitespace_is_kept_whole()
    {
        $command = new SignatureCommand('cmd {--time=10:30 : 時間}');

        $this->assertSame('10:30', $command->toArray()['options']['time']);
    }

    public function test_url_default_with_colon_not_followed_by_whitespace_is_kept_whole()
    {
        $command = new SignatureCommand('cmd {--url=https://x : 端點}');

        $this->assertSame('https://x', $command->toArray()['options']['url']);
    }

    public function test_option_with_no_colon_is_used_as_is()
    {
        $command = new SignatureCommand('cmd {--name=}');

        $this->assertArrayHasKey('name', $command->toArray()['options']);
        $this->assertNull($command->toArray()['options']['name']);
    }
}
