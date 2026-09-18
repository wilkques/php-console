<?php

namespace Wilkques\Console\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Wilkques\Console\Console;
use Wilkques\Container\Container;
use Wilkques\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetContainerSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetContainerSingleton();

        parent::tearDown();
    }

    /**
     * Reset the Container's static singleton between tests so that a test
     * exercising the global console() helper (which resolves through
     * Container::getInstance()) can never leak state into another test.
     */
    protected function resetContainerSingleton()
    {
        if (method_exists(Container::class, 'setInstance')) {
            Container::setInstance(null);

            return;
        }

        $reflection = new \ReflectionClass(Container::class);

        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Build a fresh, isolated Console instance backed by its own Container
     * and Filesystem (never the process-wide singleton), so tests cannot
     * leak command mappings/helpers into one another.
     *
     * @return \Wilkques\Console\Console
     */
    protected function makeConsole()
    {
        return new Console(new Container, new Filesystem);
    }

    /**
     * Read a protected/private property off of an object (or a static
     * property off of a class) via reflection.
     *
     * Several tests need to inspect internal state ($helpers,
     * $commandMapping, $buildStack, ...) that has no public accessor.
     *
     * @param object|string $objectOrClass
     * @param string        $property
     *
     * @return mixed
     */
    protected function peek($objectOrClass, $property)
    {
        $reflection = new \ReflectionClass($objectOrClass);

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        if (is_object($objectOrClass)) {
            return $prop->getValue($objectOrClass);
        }

        return $prop->getValue();
    }

    /**
     * Invoke a protected/private method on an object via reflection.
     *
     * @param object $object
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function invoke($object, $method, array $args = array())
    {
        $reflection = new \ReflectionClass($object);

        $m = $reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $args);
    }
}
