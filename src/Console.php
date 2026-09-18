<?php

namespace Wilkques\Console;

use Wilkques\Container\Container;
use Wilkques\Filesystem\Filesystem;
use Wilkques\Console\Exceptions\CommandNotFoundException;
use Wilkques\Console\Exceptions\ConsoleException;
use Wilkques\Console\Exceptions\DuplicateCommandException;
use Wilkques\Console\Exceptions\InvalidArgumentException;
use Wilkques\Console\Support\HelperListFormatter;

class Console
{
    /**
     * helpers
     *
     * @var array
     */
    protected $helpers = array();

    /**
     * command mapping
     *
     * @var array
     */
    protected $commandMapping = array();

    /**
     * console path
     *
     * @var string
     */
    protected $commandRootPath = './Console';

    /**
     * composer.json path
     *
     * @var string
     */
    protected $composerPath = './composer.json';

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(Container $container, Filesystem $filesystem)
    {
        $this->container = $container;

        $this->filesystem = $filesystem;

        // N20 — a console application's diagnostics belong on stderr, not
        // stdout, regardless of the SAPI's configured default. This also
        // means an exception this library intentionally lets escape
        // (e.g. an unknown command) still surfaces on stderr -- with a
        // non-zero exit code -- if the calling script does not catch it
        // itself, without this library ever calling exit()/die().
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            ini_set('display_errors', 'stderr');
        }
    }

    /**
     * Resolve (and share) the console application instance.
     *
     * N23 — bound as a singleton so repeated calls to console() return the
     * exact same instance instead of a fresh one each time.
     *
     * @return static
     */
    public static function make()
    {
        $container = Container::getInstance();

        if (!$container->bound(__CLASS__)) {
            $container->singleton(__CLASS__);
        }

        return $container->make(__CLASS__);
    }

    /**
     * @param string $root
     *
     * @return static
     */
    public function setCommandRootPath($commandRootPath)
    {
        $this->commandRootPath = $commandRootPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getCommandRootPath()
    {
        return $this->commandRootPath;
    }

    /**
     * Register a single command with the console application.
     *
     * $object may be omitted (the container builds $abstract itself), a
     * Closure (used as the binding's factory), or an already-built
     * Commandable/Command instance (registered directly as an instance so
     * it never has to pass back through the container's build pipeline).
     *
     * @param string $abstract
     * @param \Closure|\Wilkques\Console\Contracts\Commandable|\Wilkques\Console\Command|null $object
     *
     * @return static
     */
    public function register($abstract, $object = null)
    {
        $concrete = $this->fireAbstract($abstract, $object);

        $this->helpers[] = $this->helpersBuilding($abstract, $concrete);

        return $this;
    }

    /**
     * @return static
     */
    public function boot()
    {
        $files = $this->scanConsoleDir(
            $this->filesystem->directories($this->getCommandRootPath())
        );

        foreach ($files as $path) {
            $abstract = $this->getCommandClass($path);

            if (!$this->isCommandClass($abstract)) {
                continue;
            }

            $this->register($abstract);
        }

        return $this;
    }

    /**
     * N13 — only concrete Wilkques\Console\Command subclasses discovered
     * under the command root should be instantiated; abstract base
     * classes, traits, interfaces, or unrelated classes must be skipped.
     *
     * @param string $class
     *
     * @return bool
     */
    protected function isCommandClass($class)
    {
        if (!class_exists($class)) {
            return false;
        }

        if (!is_subclass_of($class, __NAMESPACE__ . '\\Command')) {
            return false;
        }

        $reflection = new \ReflectionClass($class);

        return $reflection->isInstantiable();
    }

    /**
     * @param string $abstract
     * @param \Closure|\Wilkques\Console\Contracts\Commandable|\Wilkques\Console\Command|null $concrete
     *
     * @return \Wilkques\Console\Contracts\Commandable|\Wilkques\Console\Command
     */
    protected function fireAbstract($abstract, $concrete = null)
    {
        if ($concrete instanceof \Closure) {
            $this->container->scoped($abstract, $concrete);

            return $this->container->make($abstract);
        }

        // N5 — an already-built instance is registered directly rather
        // than being handed to the container's build pipeline (which only
        // knows how to build from a class name or a Closure and would
        // otherwise raise a hard TypeError deep inside getAlias()).
        if (is_object($concrete)) {
            $this->container->instance($abstract, $concrete);

            return $concrete;
        }

        $instance = $this->container->make($abstract);

        $this->container->instance($abstract, $instance);

        return $instance;
    }

    /**
     * @param string $abstract
     * @param \Wilkques\Console\Contracts\Commandable|\Wilkques\Console\Command $concrete
     *
     * @return array
     */
    protected function helpersBuilding($abstract, $concrete)
    {
        $helpers = $concrete->getHelper();

        $this->setCommandMapping($helpers['command'], $abstract);

        return $helpers;
    }

    /**
     * command mapping
     *
     * @param string $command
     * @param string $class
     *
     * @return static
     *
     * @throws \Wilkques\Console\Exceptions\DuplicateCommandException
     */
    public function setCommandMapping($command, $class)
    {
        if (array_key_exists($command, $this->commandMapping)) {
            throw new DuplicateCommandException(
                "Command \"{$command}\" is already registered to \"{$this->commandMapping[$command]}\"."
            );
        }

        $this->commandMapping[$command] = $class;

        return $this;
    }

    /**
     * Handle a raw argv-style command array and return a process exit code.
     *
     * This is the public entry point and never lets anything escape: it
     * never calls exit()/die() itself, and it never lets a
     * \Wilkques\Console\Exceptions\ConsoleException propagate out. Errors
     * that occur while resolving/validating the command (unknown command,
     * bad arguments, undeclared options, ...) are thrown by execute() as
     * ConsoleException subclasses; handle() catches them here, writes a
     * single clean message to stderr, and returns the exception's exit
     * code instead of a raw fatal + exit 255. An exception raised from
     * inside the resolved command's own handle() is caught even earlier,
     * inside execute() itself, so that a misbehaving command cannot crash
     * the whole process either.
     *
     * @param array $commands
     *
     * @return int
     */
    public function handle($commands)
    {
        try {
            return $this->execute($commands);
        } catch (ConsoleException $e) {
            $this->writeToStderr($e->getMessage());

            return $e->getExitCode();
        }
    }

    /**
     * Programmatically dispatch another registered command and return its
     * exit code — what a command's handle() calls (via Command::call(),
     * which delegates here) to invoke another command.
     *
     * Unlike handle(), this does NOT catch ConsoleException: an unknown
     * command name or a bad argument/option here is a mistake in the
     * CALLING code (a typo'd command name, a bad hard-coded argument),
     * not user input, so it should fail loudly right where the mistake
     * is rather than be silently swallowed into a generic non-zero exit
     * code somewhere inside whatever this call happened to be nested in.
     * A target command's own handle() failing, on the other hand, is
     * converted to a plain exit code exactly like top-level dispatch —
     * see execute().
     *
     * @param string $command
     * @param array  $parameters argv-style tokens, e.g. array('somearg', '--force', '--name=value')
     *
     * @return int
     *
     * @throws \Wilkques\Console\Exceptions\ConsoleException
     */
    public function call($command, array $parameters = array())
    {
        return $this->execute(array_merge(array($command), $parameters));
    }

    /**
     * @param array $commands
     *
     * @return int
     *
     * @throws \Wilkques\Console\Exceptions\ConsoleException
     */
    protected function execute($commands)
    {
        $type = array_shift($commands);

        // N19 — strict check instead of a truthiness check, so a command
        // literally named "0" (falsy in PHP) can still be dispatched.
        if ($type === null || $type === '') {
            $this->getHelpers();

            return 0;
        }

        if (!array_key_exists($type, $this->commandMapping)) {
            throw new CommandNotFoundException("Command \"{$type}\" is not defined.");
        }

        /** @var \Wilkques\Console\Contracts\Commandable|\Wilkques\Console\Command */
        $abstract = $this->container->make($this->commandMapping[$type]);

        // Only Command (not just any Commandable) can call() another
        // command — give it a back-reference so it can, without widening
        // the Commandable contract itself.
        if (method_exists($abstract, 'setConsole')) {
            $abstract->setConsole($this);
        }

        $signature = $abstract->toArray();

        $commandFlagArgument = $this->handleCommands($commands);

        $abstract->setOrigins($commandFlagArgument)->setOptions(
            $this->handleOptions($commandFlagArgument['options'], $signature['options'])
        )->setArguments(
            $this->handleArguments($commandFlagArgument['arguments'], $abstract->getArgumentDefinitions())
        );

        try {
            $exitCode = $abstract->handle();
        } catch (ConsoleException $e) {
            $this->writeToStderr($e->getMessage());

            return $e->getExitCode();
        } catch (\Exception $e) {
            $this->writeToStderr($e->getMessage());

            return 1;
        } catch (\Throwable $e) {
            $this->writeToStderr($e->getMessage());

            return 1;
        }

        // clean scoped
        $this->container->forgetScopedInstances();

        return is_int($exitCode) ? $exitCode : 0;
    }

    /**
     * Print the registered command list to stdout, Laravel `php artisan`
     * (no arguments) style — see HelperListFormatter.
     */
    public function getHelpers()
    {
        echo HelperListFormatter::format($this->helpers);
    }

    /**
     * scan console dir
     *
     * @param string|string[] $dirs
     *
     * @return array
     */
    protected function scanConsoleDir($dirs)
    {
        $item = array();

        foreach ($dirs as $path) {
            if (!$path instanceof \SplFileInfo)
                $path = new \SplFileInfo($path);

            if ($path->isDir()) {
                $item = array_merge($item, $this->scanConsoleDir(
                    $this->filesystem->searchInDirectory($path)
                ));

                continue;
            }

            $extension = $path->getExtension();

            $extension = strtolower($extension);

            if ($extension == 'php') {
                $item[] = $path->getPathname();
            }
        }

        return $item;
    }

    /**
     * @param string $composerPath
     *
     * @return static
     */
    public function setComposerPath($composerPath = './composer.json')
    {
        $this->composerPath = $composerPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getComposerPath()
    {
        return $this->composerPath;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function psr4($path)
    {
        $composerPath = $this->getComposerPath();

        if ($this->filesystem->exists($composerPath)) {
            $jsonString = file_get_contents($composerPath);

            $json = json_decode($jsonString, true);

            if (array_key_exists('autoload', $json) && array_key_exists('psr-4', $json['autoload'])) {
                foreach ($json['autoload']['psr-4'] as $reNamespace => $namespace) {
                    if (strtok($path, $namespace)) {
                        $path = str_replace($namespace, $reNamespace, $path);
                    }
                }
            }
        }

        return $path;
    }

    /**
     * Get the full command class name for a given command.
     *
     * N3 — only the leading occurrence of the namespace prefix is
     * stripped from the fully-converted class string, not every
     * occurrence, so a class name that happens to contain the namespace
     * as a substring (e.g. "AppleCommand" containing "App") is no longer
     * corrupted.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function getCommandClass($path)
    {
        $path = $this->psr4($path);

        $namespace = $this->getNamespace($path);

        $class = str_replace(
            array('./', '.php', '/'),
            array('\\', '', '\\'),
            $path
        );

        $stripped = preg_replace('/^' . preg_quote($namespace, '/') . '/', '', $class, 1);

        return $namespace . $stripped;
    }

    /**
     * Get the namespace for the command.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function getNamespace($path)
    {
        $namespace = trim(implode('\\', array_slice(explode('\\', $path), 0, -1)), '\\');

        if (!$namespace) {
            throw new \InvalidArgumentException("Namespace not found in file: {$path}. Please add a namespace to your command and try again.");
        }

        return str_replace(array('.', '/'), array('', '\\'), $namespace);
    }

    /**
     * Parsing Command-Line Arguments and Options
     *
     * @param array $commands
     *
     * @return array
     */
    protected function handleCommands($commands)
    {
        return \Wilkques\Console\Parser::parser($commands);
    }

    /**
     * @param array $commandFlag raw options parsed off the CLI
     * @param array $signatureFlag option name => default, from Command::toArray()['options']
     *
     * @return array
     *
     * @throws \Wilkques\Console\Exceptions\InvalidArgumentException
     */
    protected function handleOptions($commandFlag, $signatureFlag)
    {
        foreach ($commandFlag as $key => $value) {
            if (!array_key_exists($key, $signatureFlag)) {
                throw new InvalidArgumentException(sprintf('The "--%s" option does not exist.', $key));
            }
        }

        // N17 — a "+" union (rather than array_merge()) preserves
        // numeric-looking string keys instead of renumbering them, and
        // lets values actually supplied on the CLI win over the
        // signature's declared defaults.
        return $commandFlag + $signatureFlag;
    }

    /**
     * @param array $commandArgument raw positional values typed on the CLI
     * @param array $argumentDefinitions from Command::getArgumentDefinitions():
     *                                   array('name', 'optional', 'default', 'array')
     *
     * @return array
     *
     * @throws \Wilkques\Console\Exceptions\InvalidArgumentException
     */
    protected function handleArguments($commandArgument, $argumentDefinitions)
    {
        $given = count($commandArgument);

        $lastDefinition = end($argumentDefinitions);

        $lastIsArray = $lastDefinition ? $lastDefinition['array'] : false;

        if (!$lastIsArray && $given > count($argumentDefinitions)) {
            // Not array_column(): it's a native function only from PHP
            // 5.5+, and this package's floor is 5.3.
            $names = array();

            foreach ($argumentDefinitions as $definition) {
                $names[] = $definition['name'];
            }

            throw new InvalidArgumentException(sprintf(
                'Too many arguments, expected arguments "%s".',
                implode('", "', $names)
            ));
        }

        $missing = array();

        foreach ($argumentDefinitions as $index => $definition) {
            if (!$definition['optional'] && $index >= $given) {
                $missing[] = $definition['name'];
            }
        }

        if ($missing) {
            throw new InvalidArgumentException(sprintf('Not enough arguments (missing: "%s").', implode('", "', $missing)));
        }

        $result = array();

        $position = 0;

        foreach ($argumentDefinitions as $definition) {
            if ($definition['array']) {
                $result[$definition['name']] = array_slice($commandArgument, $position);

                $position = $given;

                continue;
            }

            if ($position < $given) {
                $result[$definition['name']] = $commandArgument[$position];

                $position++;
            } else {
                $result[$definition['name']] = $definition['default'];
            }
        }

        return $result;
    }

    /**
     * Write an error message to stderr.
     *
     * N20 — uses the php://stderr stream wrapper instead of the STDERR
     * constant, which is undefined outside the CLI SAPI.
     *
     * @param string $message
     *
     * @return void
     */
    protected function writeToStderr($message)
    {
        $stream = fopen('php://stderr', 'w');

        fwrite($stream, $message . PHP_EOL);

        fclose($stream);
    }
}
