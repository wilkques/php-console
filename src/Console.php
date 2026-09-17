<?php

namespace Wilkques\Console;

use Wilkques\Container\Container;
use Wilkques\Filesystem\Filesystem;

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
    }

    /**
     * @return static
     */
    public static function make()
    {
        $container = Container::getInstance();

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
     * @param string $abstract
     * @param \Console\Contracts\Commandable|\Willis\Console\Command|null $object
     * 
     * @return static
     */
    public function register($abstract, $object = null)
    {
        $this->helpers = $this->helpersBuilding($abstract, $this->fireAbstract($abstract, $object));

        return $this;
    }

    /**
     * @return static
     */
    public function boot()
    {
        $this->helpers = array_reduce(
            $this->scanConsoleDir(
                $this->filesystem->directories($this->getCommandRootPath())
            ),
            function ($item, $path) {
                $abstract = $this->getCommandClass($path);

                $item[] = $this->helpersBuilding($abstract, $this->fireAbstract($abstract));

                return $item;
            }
        );

        return $this;
    }

    /**
     * @param string $abstract
     * @param callable $callBack
     * 
     * @return \Willis\Console\Contracts\Commandable|\Willis\Console\Command
     */
    protected function fireAbstract($abstract, $callBack = null)
    {
        if ($callBack) {
            $this->container->scoped($abstract, $callBack);

            return $this->container->make($abstract);
        }

        $instance = $this->container->make($abstract);

        $this->container->instance($abstract, $instance);

        return $instance;
    }

    /**
     * @param string $abstract
     * @param \Willis\Console\Contracts\Commandable|\Willis\Console\Command $concrete
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
     */
    public function setCommandMapping($command, $class)
    {
        $this->commandMapping[$command] = $class;

        return $this;
    }

    /**
     * handle commands
     * 
     * @param array $command
     */
    public function handle($commands)
    {
        $type = array_shift($commands);

        if (!$type) {
            echo $this->getHelpers();

            exit;
        }

        !array_key_exists($type, $this->commandMapping) && die("no command");

        /** @var \Console\Contracts\Commandable|\Willis\Console\Command */
        $abstract = $this->container->make($this->commandMapping[$type]);

        $signaturet = $abstract->toArray();

        $commandFlagArgument = $this->handleCommands($commands);

        $abstract->setOrigins($commandFlagArgument)->setOptions(
            $this->handleOptions($commandFlagArgument['options'], $signaturet['options'])
        )->setArguments(
            $this->handleArguments($commandFlagArgument['arguments'], $signaturet['arguments'])
        )->handle();

        // clean scoped
        $this->container->forgetScopedInstances();
    }

    /**
     * print helper
     */
    public function getHelpers()
    {
        $climate = new \League\CLImate\CLImate;

        $climate->table($this->helpers);
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
        $item = [];

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

        return $namespace . str_replace($namespace, '', $class);
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
     * @param array $commandFlag
     * @param array $signaturetFlag
     * 
     * @return array
     */
    protected function handleOptions($commandFlag, $signaturetFlag)
    {
        return array_merge($signaturetFlag, $commandFlag);
    }

    /**
     * @param array $commandArgument
     * @param array $signaturetArgument
     * 
     * @return array
     */
    protected function handleArguments($commandArgument, $signaturetArgument)
    {
        return empty($commandArgument) ? array() :
            array_combine(
                $signaturetArgument,
                $commandArgument
            );
    }
}
