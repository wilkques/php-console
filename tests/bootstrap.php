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
