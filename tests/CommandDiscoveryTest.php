<?php

namespace Wilkques\Console\Tests;

use Wilkques\Console\Console;
use Wilkques\Console\Exceptions\ConsoleException;
use Wilkques\Console\Exceptions\DuplicateCommandException;
use Wilkques\Console\Tests\Fixtures\Commands\SuccessCommand;
use Wilkques\Console\Tests\Fixtures\Commands\FailingCommand;

/**
 * Console::boot() scans a command directory, resolves each file to a
 * FQCN via the root composer.json's psr-4 map, and instantiates every
 * command it finds; Console::register() does the same for one class at
 * a time. This suite covers discovery, duplicate detection (C3), the
 * register()/boot() helpers-shape bug (C8), and several bugs found in
 * a follow-up audit (N3, N4, N5, N13, N20, N23).
 */
class CommandDiscoveryTest extends TestCase
{
    /** @var string */
    private $composerPath;

    protected function additionalSetUp()
    {
        $this->composerPath = tempnam(sys_get_temp_dir(), 'console_test_composer_') . '.json';

        file_put_contents($this->composerPath, json_encode(array(
            'autoload' => array(
                'psr-4' => array(
                    'Wilkques\\Console\\Tests\\Fixtures\\ConsoleCommands\\' => __DIR__ . '/Fixtures/ConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\DuplicateConsoleCommands\\' => __DIR__ . '/Fixtures/DuplicateConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\MixedConsoleCommands\\' => __DIR__ . '/Fixtures/MixedConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\EmptyConsoleCommands\\' => __DIR__ . '/Fixtures/EmptyConsoleCommands/',
                ),
            ),
        )));
    }

    protected function additionalTearDown()
    {
        @unlink($this->composerPath);
    }

    // -----------------------------------------------------------------
    // Basic discovery
    // -----------------------------------------------------------------

    public function test_boot_discovers_commands_under_the_command_root()
    {
        $console = $this->makeConsole()
            ->setCommandRootPath(__DIR__ . '/Fixtures/ConsoleCommands')
            ->setComposerPath($this->composerPath);

        $console->boot();

        $mapping = $this->peek($console, 'commandMapping');

        $this->assertArrayHasKey('greet', $mapping);
        $this->assertArrayHasKey('farewell', $mapping);
        $this->assertCount(2, $mapping);
    }

    // -----------------------------------------------------------------
    // C3 — duplicate command names
    // -----------------------------------------------------------------

    public function test_boot_raises_duplicate_command_exception_for_colliding_names()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\DuplicateCommandException');

        $console = $this->makeConsole()
            ->setCommandRootPath(__DIR__ . '/Fixtures/DuplicateConsoleCommands')
            ->setComposerPath($this->composerPath);

        $console->boot();
    }

    public function test_register_raises_duplicate_command_exception_for_colliding_names()
    {
        $this->expectExceptionCompat('Wilkques\Console\Exceptions\DuplicateCommandException');

        $console = $this->makeConsole();
        $console->register('Wilkques\Console\Tests\Fixtures\DuplicateConsoleCommands\FooCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\DuplicateConsoleCommands\BarCommand');
    }

    // -----------------------------------------------------------------
    // C8 — register() vs boot() must leave $helpers in the same shape
    // -----------------------------------------------------------------

    public function test_two_successive_register_calls_leave_two_helpers_and_two_mappings()
    {
        $console = $this->makeConsole();

        $console->register('Wilkques\Console\Tests\Fixtures\Commands\SuccessCommand');
        $console->register('Wilkques\Console\Tests\Fixtures\Commands\FailingCommand');

        $helpers = $this->peek($console, 'helpers');
        $mapping = $this->peek($console, 'commandMapping');

        $this->assertCount(2, $helpers, '$helpers must be an array of helper arrays, one per registered command.');
        $this->assertCount(2, $mapping);
        $this->assertArrayHasKey('success', $mapping);
        $this->assertArrayHasKey('fail', $mapping);
    }

    // -----------------------------------------------------------------
    // N3 — getCommandClass() must only strip the psr-4 prefix, not any
    // occurrence of it anywhere in the string.
    // -----------------------------------------------------------------

    /**
     * Not a @dataProvider: PHPUnit's doc-comment annotation providers were
     * eventually replaced by PHP 8+-only `#[DataProvider]` attributes,
     * which this suite (running under PHP 5.5+, "phpunit/phpunit": "*")
     * can't rely on either — a plain loop over the same cases works
     * identically on every PHPUnit version.
     */
    public function test_get_command_class_only_strips_the_leading_psr4_prefix()
    {
        $console = $this->makeConsole()->setComposerPath(__DIR__ . '/Fixtures/composer-app-map.json');

        $cases = array(
            'apple (namespace is a substring of the class name)' => array(
                'app/Console/AppleCommand.php',
                'App\\Console\\AppleCommand',
            ),
            'app-version (namespace prefix reappears mid-name)' => array(
                'app/Console/AppVersionCommand.php',
                'App\\Console\\AppVersionCommand',
            ),
            'nested Apps directory (namespace reappears as a path segment)' => array(
                'app/Console/Apps/SyncCommand.php',
                'App\\Console\\Apps\\SyncCommand',
            ),
            'member (no accidental re-match, correct today only by luck)' => array(
                'app/Console/MemberCommand.php',
                'App\\Console\\MemberCommand',
            ),
        );

        foreach ($cases as $label => $case) {
            list($path, $expectedClass) = $case;

            $class = $this->invoke($console, 'getCommandClass', array($path));

            $this->assertSame($expectedClass, $class, $label);
        }
    }

    // -----------------------------------------------------------------
    // N4 — booting an empty command directory must not blow up
    // -----------------------------------------------------------------

    public function test_boot_with_empty_command_directory_leaves_helpers_as_empty_array()
    {
        $console = $this->makeConsole()
            ->setCommandRootPath(__DIR__ . '/Fixtures/EmptyConsoleCommands')
            ->setComposerPath($this->composerPath);

        $console->boot();

        $this->assertSame(array(), $this->peek($console, 'helpers'));
    }

    // -----------------------------------------------------------------
    // N13 — non-command PHP files under the command root must be
    // skipped rather than instantiated unconditionally.
    // -----------------------------------------------------------------

    public function test_boot_skips_non_command_php_files_in_the_command_directory()
    {
        $console = $this->makeConsole()
            ->setCommandRootPath(__DIR__ . '/Fixtures/MixedConsoleCommands')
            ->setComposerPath($this->composerPath);

        $console->boot();

        $mapping = $this->peek($console, 'commandMapping');

        $this->assertArrayHasKey('ping', $mapping);
        $this->assertCount(1, $mapping, 'NotACommand.php and the abstract base class must be skipped, not instantiated.');
    }

    // -----------------------------------------------------------------
    // N5 — register() with a Closure must work; register() with an
    // already-built Command instance must not be a hard TypeError.
    // -----------------------------------------------------------------

    public function test_register_with_a_closure_works()
    {
        $console = $this->makeConsole();

        $console->register('Wilkques\Console\Tests\Fixtures\Commands\SuccessCommand', function () {
            return new SuccessCommand;
        });

        $mapping = $this->peek($console, 'commandMapping');

        $this->assertArrayHasKey('success', $mapping);
    }

    public function test_register_with_an_already_built_instance_does_not_raise_a_type_error()
    {
        $console = $this->makeConsole();

        try {
            $console->register('Wilkques\Console\Tests\Fixtures\Commands\SuccessCommand', new SuccessCommand);

            $mapping = $this->peek($console, 'commandMapping');

            $this->assertArrayHasKey('success', $mapping);
        } catch (ConsoleException $e) {
            // Also acceptable: a clear, documented console-level error
            // instead of a raw TypeError from deep inside the container.
            $this->addToAssertionCount(1);
        }
    }

    // -----------------------------------------------------------------
    // N23 — console() should resolve to a shared/singleton instance.
    // -----------------------------------------------------------------

    public function test_console_helper_returns_the_same_instance_every_time()
    {
        $this->assertSame(console(), console());
    }

    // -----------------------------------------------------------------
    // N20 / REGRESSION 2 — an unknown command must write a clean message
    // to stderr (not stdout) and yield a non-zero exit code, the same way
    // a real entry point (e.g. bin/artisan calling
    // exit(console()->handle($argv))) would surface it. Console::handle()
    // itself never calls exit(); it catches the ConsoleException and
    // returns its exit code, so this script exits on that return value
    // exactly like a real caller would.
    // -----------------------------------------------------------------

    public function test_abort_writes_its_message_to_stderr_not_stdout()
    {
        $bootstrap = var_export(__DIR__ . '/bootstrap.php', true);

        $script = <<<PHP
require {$bootstrap};
use Wilkques\Console\Console;
use Wilkques\Container\Container;
use Wilkques\Filesystem\Filesystem;
\$console = new Console(new Container, new Filesystem);
exit(\$console->handle(array('nope:such')));
PHP;

        // proc_open()'s array-form command (an argv-style list instead of a
        // shell string) is PHP 7.4+ only; composer.json's floor here is
        // 5.3, so build the string form by hand with escapeshellarg(),
        // which has worked the same way since forever.
        //
        // PHP_BINARY itself is only defined from PHP 5.4+ — on 5.3 an
        // undefined constant silently evaluates to its own name as a
        // string ("PHP_BINARY"), which the shell then fails to find.
        $phpBinary = defined('PHP_BINARY') ? PHP_BINARY : PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';

        $command = escapeshellarg($phpBinary) . ' -r ' . escapeshellarg($script);

        $process = proc_open(
            $command,
            array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
            $pipes
        );

        $this->assertIsResourceCompat($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertNotSame(0, $exitCode, 'An unknown command should exit non-zero.');
        $this->assertStringContainsStringCompat('nope:such', $stderr);
        $this->assertSame('', trim($stdout), 'The error message must not be written to stdout.');
    }
}
