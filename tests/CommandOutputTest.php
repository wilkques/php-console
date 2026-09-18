<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Tests\Fixtures\SignatureCommand;

/**
 * Command::line()/info()/error()/warn()/comment() — plain/colored output
 * helpers a command's own handle() can call, distinct from Console's N20
 * framework-level diagnostics (unknown command, bad arguments, ...),
 * which always go to stderr regardless of these.
 *
 * PHPUnit captures stdout during a test run rather than attaching it to a
 * real terminal, so supportsColors() is naturally false throughout this
 * suite (stream_isatty()/posix_isatty() both say "not a tty") — every
 * assertion here can expect plain, uncolored text without having to fake
 * a tty.
 *
 * No closures capturing $this here: PHP 5.3 closures don't auto-bind
 * $this from the enclosing method, so ob_start()/ob_get_clean() are
 * called directly in each test instead of through a captureOutput()
 * helper that would need one.
 */
class CommandOutputTest extends TestCase
{
    /** @var \Wilkques\Console\Tests\Fixtures\SignatureCommand */
    protected $command;

    protected function additionalSetUp()
    {
        $this->command = new SignatureCommand('noop');
    }

    public function test_line_writes_the_message_with_no_color_codes()
    {
        ob_start();
        $this->command->line('hello');
        $output = ob_get_clean();

        $this->assertSame('hello' . PHP_EOL, $output);
    }

    public function test_info_writes_the_message_uncolored_when_not_a_tty()
    {
        ob_start();
        $this->command->info('all good');
        $output = ob_get_clean();

        $this->assertSame('all good' . PHP_EOL, $output);
    }

    public function test_error_writes_the_message_uncolored_when_not_a_tty()
    {
        ob_start();
        $this->command->error('something broke');
        $output = ob_get_clean();

        $this->assertSame('something broke' . PHP_EOL, $output);
    }

    public function test_warn_writes_the_message_uncolored_when_not_a_tty()
    {
        ob_start();
        $this->command->warn('careful');
        $output = ob_get_clean();

        $this->assertSame('careful' . PHP_EOL, $output);
    }

    public function test_comment_writes_the_message_uncolored_when_not_a_tty()
    {
        ob_start();
        $this->command->comment('fyi');
        $output = ob_get_clean();

        $this->assertSame('fyi' . PHP_EOL, $output);
    }

    public function test_supports_colors_is_false_during_a_phpunit_run()
    {
        $this->assertFalse($this->invoke($this->command, 'supportsColors'));
    }

    public function test_supports_colors_is_false_when_no_color_env_is_set()
    {
        putenv('NO_COLOR=1');

        try {
            $this->assertFalse($this->invoke($this->command, 'supportsColors'));
        } catch (\Exception $e) {
            putenv('NO_COLOR');

            throw $e;
        }

        putenv('NO_COLOR');
    }
}
