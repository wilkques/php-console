<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Parser;

/**
 * Wilkques\Console\Parser::parser() takes an argv-style array of raw CLI
 * tokens and splits them into ['options' => [...], 'arguments' => [...]].
 *
 * These are mostly regression guards for bugs already fixed in earlier
 * rounds (see Parser.php's inline comments), plus C4 (the "--"
 * end-of-options terminator), which is still outstanding.
 */
class ParserTest extends TestCase
{
    public function test_short_option_strips_its_leading_dash()
    {
        $result = Parser::parser(array('-v'));

        $this->assertSame(array('v' => true), $result['options']);
        $this->assertSame(array(), $result['arguments']);
    }

    public function test_long_option_value_keeps_internal_spaces()
    {
        $result = Parser::parser(array('--name=  spaced  '));

        $this->assertSame('  spaced  ', $result['options']['name']);
    }

    public function test_debug_equals_false_becomes_boolean_false()
    {
        $result = Parser::parser(array('--debug=false'));

        $this->assertSame(false, $result['options']['debug']);
    }

    public function test_debug_equals_true_becomes_boolean_true()
    {
        $result = Parser::parser(array('--debug=true'));

        $this->assertSame(true, $result['options']['debug']);
    }

    public function test_bare_long_flag_defaults_to_true()
    {
        $result = Parser::parser(array('--some-flag'));

        $this->assertSame(array('some-flag' => true), $result['options']);
    }

    public function test_negative_number_without_terminator_is_still_an_option()
    {
        // Existing behaviour: without a "--" terminator, anything starting
        // with "-" is treated as an option, including things that look
        // like negative numbers.
        $result = Parser::parser(array('-5'));

        $this->assertSame(array('5' => true), $result['options']);
        $this->assertSame(array(), $result['arguments']);
    }

    public function test_multiple_options_and_arguments_are_split_correctly()
    {
        $result = Parser::parser(array('--flag', 'first', '--name=bob', 'second'));

        $this->assertSame(array('flag' => true, 'name' => 'bob'), $result['options']);
        $this->assertSame(array('first', 'second'), $result['arguments']);
    }
}
