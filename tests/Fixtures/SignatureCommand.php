<?php

namespace Wilkques\Console\Tests\Fixtures;

use Wilkques\Console\Command;

/**
 * A bare command whose $signature/$description are supplied directly, so
 * tests can probe Command::toArray() / getArgumentDefinitions() /
 * getHelper() / regexMatches() against arbitrary signature strings
 * without needing container registration or command discovery.
 */
class SignatureCommand extends Command
{
    public function __construct($signature = null, $description = null)
    {
        if ($signature !== null) {
            $this->signature = $signature;
        }

        if ($description !== null) {
            $this->description = $description;
        }
    }

    public function handle()
    {
        return 0;
    }
}
