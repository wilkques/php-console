<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Tests\Fixtures\SignatureCommand;

/**
 * Command::ask()/confirm()/choice() — read from setInputStream() (a
 * php://memory stream fed with canned answers here) instead of the
 * process's real STDIN, which is what makes these testable at all.
 */
class CommandInteractiveTest extends TestCase
{
    /** @var \Wilkques\Console\Tests\Fixtures\SignatureCommand */
    protected $command;

    protected function additionalSetUp()
    {
        $this->command = new SignatureCommand('noop');
    }

    /**
     * @param string $input
     */
    protected function feed($input)
    {
        $stream = fopen('php://memory', 'r+');

        fwrite($stream, $input);
        rewind($stream);

        $this->command->setInputStream($stream);
    }

    public function test_ask_returns_the_trimmed_answer()
    {
        $this->feed("  Alice  \n");

        ob_start();
        $answer = $this->command->ask('Name');
        ob_get_clean();

        $this->assertSame('Alice', $answer);
    }

    public function test_ask_returns_default_on_blank_answer()
    {
        $this->feed("\n");

        ob_start();
        $answer = $this->command->ask('Name', 'Bob');
        ob_get_clean();

        $this->assertSame('Bob', $answer);
    }

    public function test_ask_returns_default_at_eof()
    {
        $this->feed('');

        ob_start();
        $answer = $this->command->ask('Name', 'Bob');
        ob_get_clean();

        $this->assertSame('Bob', $answer);
    }

    public function test_ask_prints_the_question_and_default_hint()
    {
        $this->feed("\n");

        ob_start();
        $this->command->ask('Name', 'Bob');
        $output = ob_get_clean();

        $this->assertSame('Name [Bob]: ', $output);
    }

    public function test_confirm_accepts_y()
    {
        $this->feed("y\n");

        ob_start();
        $answer = $this->command->confirm('Proceed?');
        ob_get_clean();

        $this->assertTrue($answer);
    }

    public function test_confirm_accepts_yes_case_insensitively()
    {
        $this->feed("YES\n");

        ob_start();
        $answer = $this->command->confirm('Proceed?');
        ob_get_clean();

        $this->assertTrue($answer);
    }

    public function test_confirm_rejects_anything_else()
    {
        $this->feed("nope\n");

        ob_start();
        $answer = $this->command->confirm('Proceed?', true);
        ob_get_clean();

        $this->assertFalse($answer);
    }

    public function test_confirm_falls_back_to_default_on_blank_answer()
    {
        $this->feed("\n");

        ob_start();
        $answer = $this->command->confirm('Proceed?', true);
        ob_get_clean();

        $this->assertTrue($answer);
    }

    public function test_choice_resolves_by_numeric_index()
    {
        $this->feed("1\n");

        ob_start();
        $answer = $this->command->choice('Pick one', array('red', 'green', 'blue'));
        ob_get_clean();

        $this->assertSame('green', $answer);
    }

    public function test_choice_resolves_by_exact_value()
    {
        $this->feed("blue\n");

        ob_start();
        $answer = $this->command->choice('Pick one', array('red', 'green', 'blue'));
        ob_get_clean();

        $this->assertSame('blue', $answer);
    }

    public function test_choice_falls_back_to_default_on_blank_answer()
    {
        $this->feed("\n");

        ob_start();
        $answer = $this->command->choice('Pick one', array('red', 'green', 'blue'), 2);
        ob_get_clean();

        $this->assertSame('blue', $answer);
    }

    public function test_choice_reprompts_on_an_invalid_answer_then_accepts_the_next_valid_one()
    {
        $this->feed("nonsense\ngreen\n");

        ob_start();
        $answer = $this->command->choice('Pick one', array('red', 'green', 'blue'));
        $output = ob_get_clean();

        $this->assertSame('green', $answer);
        $this->assertStringContainsStringCompat('Invalid choice', $output);
    }

    /**
     * secret() falls back to a plain, visible read whenever
     * setInputStream() is in effect (see Command::canHideInput()) — real
     * STDIN is untouched, so there is nothing for `stty` to hide and
     * toggling it would affect the wrong stream. That fallback is what
     * makes secret() testable here at all, same as ask()/confirm()/choice().
     */
    public function test_secret_returns_the_trimmed_answer()
    {
        $this->feed("hunter2\n");

        ob_start();
        $answer = $this->command->secret('Password');
        ob_get_clean();

        $this->assertSame('hunter2', $answer);
    }

    public function test_secret_returns_null_on_blank_answer()
    {
        $this->feed("\n");

        ob_start();
        $answer = $this->command->secret('Password');
        ob_get_clean();

        $this->assertNull($answer);
    }

    public function test_secret_returns_null_at_eof()
    {
        $this->feed('');

        ob_start();
        $answer = $this->command->secret('Password');
        ob_get_clean();

        $this->assertNull($answer);
    }

    public function test_secret_prints_only_the_question_with_no_default_hint()
    {
        $this->feed("hunter2\n");

        ob_start();
        $this->command->secret('Password');
        $output = ob_get_clean();

        $this->assertSame('Password: ', $output);
    }

    public function test_can_hide_input_is_false_once_an_input_stream_is_set()
    {
        $this->feed("hunter2\n");

        $this->assertFalse($this->invoke($this->command, 'canHideInput'));
    }
}
