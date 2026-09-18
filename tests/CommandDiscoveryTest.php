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

    protected function setUp(): void
    {
        parent::setUp();

        $this->composerPath = tempnam(sys_get_temp_dir(), 'console_test_composer_') . '.json';

        file_put_contents($this->composerPath, json_encode([
            'autoload' => [
                'psr-4' => [
                    'Wilkques\\Console\\Tests\\Fixtures\\ConsoleCommands\\' => __DIR__ . '/Fixtures/ConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\DuplicateConsoleCommands\\' => __DIR__ . '/Fixtures/DuplicateConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\MixedConsoleCommands\\' => __DIR__ . '/Fixtures/MixedConsoleCommands/',
                    'Wilkques\\Console\\Tests\\Fixtures\\EmptyConsoleCommands\\' => __DIR__ . '/Fixtures/EmptyConsoleCommands/',
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->composerPath);

        parent::tearDown();
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
        $this->expectException(DuplicateCommandException::class);

        $console = $this->makeConsole()
            ->setCommandRootPath(__DIR__ . '/Fixtures/DuplicateConsoleCommands')
            ->setComposerPath($this->composerPath);

        $console->boot();
    }

    public function test_register_raises_duplicate_command_exception_for_colliding_names()
    {
        $this->expectException(DuplicateCommandException::class);

        $console = $this->makeConsole();
        $console->register(\Wilkques\Console\Tests\Fixtures\DuplicateConsoleCommands\FooCommand::class);
        $console->register(\Wilkques\Console\Tests\Fixtures\DuplicateConsoleCommands\BarCommand::class);
    }

    // -----------------------------------------------------------------
    // C8 — register() vs boot() must leave $helpers in the same shape
    // -----------------------------------------------------------------

    public function test_two_successive_register_calls_leave_two_helpers_and_two_mappings()
    {
        $console = $this->makeConsole();

        $console->register(SuccessCommand::class);
        $console->register(FailingCommand::class);

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
     * @dataProvider commandClassProvider
     */
    public function test_get_command_class_only_strips_the_leading_psr4_prefix($path, $expectedClass)
    {
        $console = $this->makeConsole()->setComposerPath(__DIR__ . '/Fixtures/composer-app-map.json');

        $class = $this->invoke($console, 'getCommandClass', [$path]);

        $this->assertSame($expectedClass, $class);
    }

    public function commandClassProvider()
    {
        return [
            'apple (namespace is a substring of the class name)' => [
                'app/Console/AppleCommand.php',
                'App\\Console\\AppleCommand',
            ],
            'app-version (namespace prefix reappears mid-name)' => [
                'app/Console/AppVersionCommand.php',
                'App\\Console\\AppVersionCommand',
            ],
            'nested Apps directory (namespace reappears as a path segment)' => [
                'app/Console/Apps/SyncCommand.php',
                'App\\Console\\Apps\\SyncCommand',
            ],
            'member (no accidental re-match, correct today only by luck)' => [
                'app/Console/MemberCommand.php',
                'App\\Console\\MemberCommand',
            ],
        ];
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

        $this->assertSame([], $this->peek($console, 'helpers'));
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

        $console->register(SuccessCommand::class, function () {
            return new SuccessCommand;
        });

        $mapping = $this->peek($console, 'commandMapping');

        $this->assertArrayHasKey('success', $mapping);
    }

    public function test_register_with_an_already_built_instance_does_not_raise_a_type_error()
    {
        $console = $this->makeConsole();

        try {
            $console->register(SuccessCommand::class, new SuccessCommand);

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
exit(\$console->handle(['nope:such']));
PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertNotSame(0, $exitCode, 'An unknown command should exit non-zero.');
        $this->assertStringContainsString('nope:such', $stderr);
        $this->assertSame('', trim($stdout), 'The error message must not be written to stdout.');
    }
}
