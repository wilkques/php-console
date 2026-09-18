<?php

// This is the standalone package repository, so the package root is one
// directory up and its own Composer autoloader lives at
// ../vendor/autoload.php. Run `composer install` at the package root
// before running the suite.
//
// NOTE: when this package is installed as a dependency it sits at
// vendor/wilkques/console/, where the *application's* autoloader is three
// directories up ('/../../../autoload.php') instead. The two copies of
// this file therefore differ on purpose — a sibling package
// (wilkques/container) broke exactly this way once. Don't "sync" this
// line between them.
require __DIR__ . '/../vendor/autoload.php';

// The autoloader above only knows about "Wilkques\Console\" -> "src/".
// Register a second, PSR-4-ish autoloader for this package's own test
// fixtures/classes so that "Wilkques\Console\Tests\Foo\Bar" resolves to
// "tests/Foo/Bar.php" relative to this file.
spl_autoload_register(function ($class) {
    $prefix = 'Wilkques\\Console\\Tests\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// composer.json's require-dev pins "phpunit/phpunit": "*", so composer
// resolves whichever major version the running PHP can actually install:
// PHPUnit 4.8 on PHP 5.5, up through whatever's newest on 8.5+. Two
// things differ across that range and both are compile-time syntax, not
// something a runtime check alone can paper over:
//
//   1. PHPUnit < 6 exposes the global \PHPUnit_Framework_TestCase; PHPUnit
//      >= 6 exposes the PSR-4 \PHPUnit\Framework\TestCase instead. Alias
//      the old name onto the new one so every test file can consistently
//      write `use PHPUnit\Framework\TestCase`.
//   2. PHPUnit versions whose TestCase::setUp()/tearDown() declare a
//      `: void` return type require every override to repeat it (PHP
//      enforces return-type covariance); versions whose setUp() declares
//      no return type at all will fatal ("must be compatible with") if an
//      override adds one PHP itself doesn't support (< 7.1) anyway. So
//      TestCase.php — the only file that overrides setUp()/tearDown() —
//      is a thin dispatcher that requires whichever variant matches
//      what's actually installed, decided here once via reflection on
//      the real, installed TestCase rather than guessing from a
//      PHP/PHPUnit version number. (Subclasses needing their own
//      per-test setup, e.g. CommandDiscoveryTest, override TestCase's
//      untyped additionalSetUp() hook instead of setUp() itself, so they
//      never need this treatment.)
if (!class_exists('PHPUnit\\Framework\\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\\Framework\\TestCase');
}

if (!defined('WILKQUES_CONSOLE_TESTS_SETUP_NEEDS_VOID')) {
    $needsVoid = false;

    // "(new Foo())->bar()" needs PHP 5.4; keep this branch itself safe
    // even below that, for symmetry with the sibling packages.
    if (method_exists('ReflectionMethod', 'hasReturnType')) {
        $setUpReflection = new ReflectionMethod('PHPUnit\\Framework\\TestCase', 'setUp');
        $needsVoid = $setUpReflection->hasReturnType();
    }

    define('WILKQUES_CONSOLE_TESTS_SETUP_NEEDS_VOID', $needsVoid);
}
