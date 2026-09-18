<?php

namespace Wilkques\Console\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Wilkques\Console\Console;
use Wilkques\Container\Container;
use Wilkques\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->resetContainerSingleton();

        $this->additionalSetUp();
    }

    protected function tearDown()
    {
        $this->additionalTearDown();

        $this->resetContainerSingleton();

        parent::tearDown();
    }

    /**
     * Hook for subclasses that need extra per-test setup (e.g.
     * CommandDiscoveryTest's temp composer.json). Deliberately NOT named
     * setUp(): PHPUnit enforces return-type covariance on setUp()/
     * tearDown() overrides, and that return type is compile-time syntax
     * that differs across the PHPUnit versions this suite runs under (see
     * tests/bootstrap.php) — a plain, un-typed hook method has no such
     * constraint, so subclasses needing it stay a single file instead of
     * needing their own With/WithoutVoid split.
     */
    protected function additionalSetUp()
    {
    }

    /**
     * @see additionalSetUp()
     */
    protected function additionalTearDown()
    {
    }

    /**
     * Reset the Container's static singleton between tests so that a test
     * exercising the global console() helper (which resolves through
     * Container::getInstance()) can never leak state into another test.
     */
    protected function resetContainerSingleton()
    {
        if (method_exists('Wilkques\\Container\\Container', 'setInstance')) {
            Container::setInstance(null);

            return;
        }

        $reflection = new \ReflectionClass('Wilkques\\Container\\Container');

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

    /**
     * PHPUnit assertion/expectation method names that have been renamed
     * across the major versions this suite runs under (see
     * tests/bootstrap.php re: "phpunit/phpunit": "*"). Unlike setUp()'s
     * return type, these are plain method calls, not syntax — a runtime
     * method_exists() check is enough, no With/WithoutVoid-style file
     * split needed.
     *
     * @param string $class
     *
     * @return void
     */
    protected function expectExceptionCompat($class)
    {
        if (method_exists($this, 'expectException')) {
            // PHPUnit >= 5.2
            $this->expectException($class);

            return;
        }

        // PHPUnit 4.x: no expectException() at all.
        $this->setExpectedException($class);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    protected function assertStringContainsStringCompat($needle, $haystack, $message = '')
    {
        if (method_exists($this, 'assertStringContainsString')) {
            // PHPUnit >= 7.5
            $this->assertStringContainsString($needle, $haystack, $message);

            return;
        }

        // PHPUnit < 7.5: assertContains() still accepted a string haystack.
        $this->assertContains($needle, $haystack, $message);
    }

    /**
     * @param mixed  $value
     * @param string $message
     *
     * @return void
     */
    protected function assertIsIntCompat($value, $message = '')
    {
        if (method_exists($this, 'assertIsInt')) {
            // PHPUnit >= 7.5
            $this->assertIsInt($value, $message);

            return;
        }

        // PHPUnit < 7.5, renamed from assertInternalType('integer', ...).
        $this->assertInternalType('integer', $value, $message);
    }

    /**
     * @param mixed  $value
     * @param string $message
     *
     * @return void
     */
    protected function assertIsResourceCompat($value, $message = '')
    {
        if (method_exists($this, 'assertIsResource')) {
            // PHPUnit >= 7.5
            $this->assertIsResource($value, $message);

            return;
        }

        // PHPUnit < 7.5, renamed from assertInternalType('resource', ...).
        $this->assertInternalType('resource', $value, $message);
    }
}
