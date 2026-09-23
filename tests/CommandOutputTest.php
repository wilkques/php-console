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

    public function test_table_echoes_the_formatted_table()
    {
        ob_start();
        $this->command->table(array('Name'), array(array('Bob')));
        $output = ob_get_clean();

        $expected = '+------+' . PHP_EOL
            . '| Name |' . PHP_EOL
            . '+------+' . PHP_EOL
            . '| Bob  |' . PHP_EOL
            . '+------+' . PHP_EOL;

        $this->assertSame($expected, $output);
    }

    /**
     * progressStart()/Advance() redraw via "\r" only when isInteractiveOutput()
     * is true, so under PHPUnit (never a real tty, per the class docblock
     * above) they produce no output at all — only progressFinish() always
     * prints its one summary line, tty or not.
     */
    public function test_progress_start_and_advance_produce_no_output_when_not_a_tty()
    {
        ob_start();
        $this->command->progressStart(5);
        $this->command->progressAdvance();
        $this->command->progressAdvance(2);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_progress_advance_and_finish_are_no_ops_before_progress_start()
    {
        ob_start();
        $this->command->progressAdvance();
        $this->command->progressFinish();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_progress_finish_prints_a_plain_summary_line_when_not_a_tty()
    {
        ob_start();
        $this->command->progressStart(4);
        $this->command->progressAdvance(4);
        $this->command->progressFinish();
        $output = ob_get_clean();

        $bar = str_repeat('=', 28);

        $this->assertSame('100% [' . $bar . '] 4/4' . PHP_EOL, $output);
    }

    public function test_progress_bar_with_zero_total_finishes_at_full_bar()
    {
        ob_start();
        $this->command->progressStart(0);
        $this->command->progressFinish();
        $output = ob_get_clean();

        $bar = str_repeat('=', 28);

        $this->assertSame('100% [' . $bar . '] 0/0' . PHP_EOL, $output);
    }

    public function test_with_progress_bar_calls_the_callback_for_each_item_and_returns_them_unchanged()
    {
        $seen = array();

        ob_start();
        $result = $this->command->withProgressBar(array('a', 'b', 'c'), function ($item, $key) use (&$seen) {
            $seen[$key] = $item;
        });
        $output = ob_get_clean();

        $this->assertSame(array('a', 'b', 'c'), $result);
        $this->assertSame(array('a', 'b', 'c'), $seen);

        $bar = str_repeat('=', 28);

        $this->assertSame('100% [' . $bar . '] 3/3' . PHP_EOL, $output);
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
