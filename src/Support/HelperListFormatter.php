<?php

namespace Wilkques\Console\Support;

use Wilkques\Helpers\Arrays;

/**
 * Formats the registered command list the way `php artisan` (no
 * arguments) does under Laravel: an "Available commands:" heading,
 * top-level (unnamespaced) commands first, then one alphabetically
 * sorted group per "namespace:" prefix, every command name padded to a
 * shared column so descriptions line up.
 *
 * A pure formatter — takes the helper array, returns a string, does no
 * I/O itself — so it's testable directly without capturing stdout (see
 * tests/Support/HelperListFormatterTest.php). Console::getHelpers() just
 * echoes what this returns.
 *
 * This replaces league/climate's CLImate::table(), which was this
 * package's only use of league/climate — and league/climate is (was) a
 * hard runtime dependency (composer.json "require", not "require-dev")
 * that itself declares "php": "^7.3 || ^8.0", silently raising this
 * package's real installable floor to 7.3 even though composer.json (and
 * src/'s own syntax) claim 5.5. Removing it drops the real floor back to
 * what's actually declared.
 *
 * The command column is padded with DisplayWidth (terminal columns), not
 * strlen() (bytes), so a command name containing CJK/fullwidth text still
 * lines up against the rest.
 */
class HelperListFormatter
{
    /**
     * @param array $helpers array of array('command' => string, 'description' => string, 'signature' => string)
     *
     * @return string
     */
    public static function format($helpers)
    {
        if (!$helpers) {
            return 'No commands registered.' . PHP_EOL;
        }

        $groups = static::group($helpers);

        $width = static::longestCommandNameLength($helpers);

        $lines = array('Available commands:');

        if (isset($groups[''])) {
            foreach ($groups[''] as $helper) {
                $lines[] = static::formatRow($helper, $width);
            }

            unset($groups['']);
        }

        ksort($groups);

        foreach ($groups as $namespace => $namespaceHelpers) {
            $lines[] = '';
            $lines[] = ' ' . $namespace;

            foreach ($namespaceHelpers as $helper) {
                $lines[] = static::formatRow($helper, $width);
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Split the command list into an ungrouped bucket (key '') plus one
     * bucket per namespace — the segment before the first ':' in the
     * command name — each sorted alphabetically by command name.
     *
     * @param array $helpers
     *
     * @return array
     */
    protected static function group($helpers)
    {
        $groups = array();

        foreach ($helpers as $helper) {
            $command = $helper['command'];

            $colon = strpos($command, ':');

            $namespace = $colon === false ? '' : substr($command, 0, $colon);

            $groups[$namespace][] = $helper;
        }

        foreach ($groups as $namespace => $namespaceHelpers) {
            $groups[$namespace] = Arrays::sort($namespaceHelpers, 'command');
        }

        return $groups;
    }

    /**
     * @param array $helpers
     *
     * @return int
     */
    protected static function longestCommandNameLength($helpers)
    {
        $length = 0;

        foreach ($helpers as $helper) {
            $length = max($length, DisplayWidth::width($helper['command']));
        }

        return $length;
    }

    /**
     * @param array $helper
     * @param int   $width
     *
     * @return string
     */
    protected static function formatRow($helper, $width)
    {
        $row = '  ' . DisplayWidth::pad($helper['command'], $width);

        if ($helper['description'] !== null && $helper['description'] !== '') {
            $row .= '  ' . $helper['description'];
        }

        return $row;
    }
}
