<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Parser;
use Wilkques\Console\Tests\Fixtures\Commands\EchoArgCommand;

/**
 * C4 — a bare "--" must terminate option parsing: everything after it is
 * a positional argument even if it looks like an option (starts with
 * "-"). Without a "--" terminator, existing behaviour (an argument
 * starting with "-" is parsed as an option) must be unchanged.
 */
class EndOfOptionsTest extends TestCase
{
    public function test_double_dash_terminates_options_at_the_parser_level()
    {
        $result = Parser::parser(array('--', '-5'));

        $this->assertSame(array(), $result['options']);
        $this->assertSame(array('-5'), $result['arguments']);
    }

    public function test_everything_after_double_dash_is_an_argument_even_if_option_like()
    {
        $result = Parser::parser(array('--flag', '--', '-x', '--not-an-option', '--also=not-an-option'));

        $this->assertSame(array('flag' => true), $result['options']);
        $this->assertSame(array('-x', '--not-an-option', '--also=not-an-option'), $result['arguments']);
    }

    public function test_double_dash_token_itself_does_not_become_an_argument()
    {
        $result = Parser::parser(array('--'));

        $this->assertSame(array(), $result['options']);
        $this->assertSame(array(), $result['arguments']);
    }

    public function test_without_double_dash_negative_number_remains_an_option()
    {
        // Regression guard: this is the pre-existing behaviour and must
        // not change when "--" support is added.
        $result = Parser::parser(array('-5'));

        $this->assertSame(array('5' => true), $result['options']);
        $this->assertSame(array(), $result['arguments']);
    }

    /**
     * End-to-end: a "-5"-shaped token after "--" must survive the whole
     * Console::handle() pipeline and be bound as the command's real
     * argument value, not swallowed as an (unknown) option.
     *
     * Isolated in its own process: until C4/C5 are fixed, the "-5" token
     * is misparsed as an unknown option, which today makes
     * Console::abort() call exit() directly -- that would otherwise kill
     * the whole PHPUnit run.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_double_dash_terminator_survives_the_full_console_pipeline()
    {
        EchoArgCommand::$captured = null;

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\EchoArgCommand');

        $console->handle(array('echoarg', '--', '-5'));

        $this->assertSame('-5', EchoArgCommand::$captured);
    }
}
