<?php

namespace Wilkques\Console\Tests\Support;

use Wilkques\Console\Support\HelperListFormatter;
use Wilkques\Console\Tests\TestCase;

class HelperListFormatterTest extends TestCase
{
    public function test_empty_list_says_no_commands_registered()
    {
        $this->assertSame('No commands registered.' . PHP_EOL, HelperListFormatter::format(array()));
    }

    public function test_single_ungrouped_command()
    {
        $helpers = array(
            array('command' => 'greet', 'description' => 'Say hello', 'signature' => 'greet'),
        );

        $expected = 'Available commands:' . PHP_EOL
            . '  greet  Say hello' . PHP_EOL;

        $this->assertSame($expected, HelperListFormatter::format($helpers));
    }

    public function test_ungrouped_commands_are_sorted_alphabetically()
    {
        $helpers = array(
            array('command' => 'zebra', 'description' => 'Z', 'signature' => 'zebra'),
            array('command' => 'apple', 'description' => 'A', 'signature' => 'apple'),
        );

        $formatted = HelperListFormatter::format($helpers);

        $this->assertLessThan(
            strpos($formatted, 'zebra'),
            strpos($formatted, 'apple'),
            'apple must be listed before zebra'
        );
    }

    public function test_namespaced_commands_are_grouped_under_a_namespace_header()
    {
        $helpers = array(
            array('command' => 'make:model', 'description' => 'Make a model', 'signature' => 'make:model'),
            array('command' => 'make:controller', 'description' => 'Make a controller', 'signature' => 'make:controller'),
        );

        $expected = 'Available commands:' . PHP_EOL
            . PHP_EOL
            . ' make' . PHP_EOL
            . '  make:controller  Make a controller' . PHP_EOL
            . '  make:model       Make a model' . PHP_EOL;

        $this->assertSame($expected, HelperListFormatter::format($helpers));
    }

    public function test_ungrouped_commands_are_listed_before_namespace_groups()
    {
        $helpers = array(
            array('command' => 'make:model', 'description' => 'Make a model', 'signature' => 'make:model'),
            array('command' => 'greet', 'description' => 'Say hello', 'signature' => 'greet'),
        );

        $formatted = HelperListFormatter::format($helpers);

        $this->assertLessThan(
            strpos($formatted, 'make:model'),
            strpos($formatted, 'greet'),
            'ungrouped commands must be listed before namespace groups'
        );
    }

    public function test_namespace_groups_are_sorted_alphabetically()
    {
        $helpers = array(
            array('command' => 'zoo:animal', 'description' => 'Z', 'signature' => 'zoo:animal'),
            array('command' => 'app:info', 'description' => 'A', 'signature' => 'app:info'),
        );

        $formatted = HelperListFormatter::format($helpers);

        $this->assertLessThan(
            strpos($formatted, ' zoo'),
            strpos($formatted, ' app'),
            'the "app" namespace group must be listed before the "zoo" namespace group'
        );
    }

    public function test_command_column_is_padded_to_the_longest_command_name()
    {
        $helpers = array(
            array('command' => 'a', 'description' => 'short', 'signature' => 'a'),
            array('command' => 'a-much-longer-command', 'description' => 'long', 'signature' => 'a-much-longer-command'),
        );

        $formatted = HelperListFormatter::format($helpers);

        $expectedRow = '  ' . str_pad('a', strlen('a-much-longer-command')) . '  short';

        $this->assertStringContainsStringCompat($expectedRow, $formatted);
    }

    public function test_command_with_no_description_has_no_trailing_padding_junk()
    {
        $helpers = array(
            array('command' => 'quiet', 'description' => '', 'signature' => 'quiet'),
        );

        $expected = 'Available commands:' . PHP_EOL
            . '  quiet' . PHP_EOL;

        $this->assertSame($expected, HelperListFormatter::format($helpers));
    }
}
