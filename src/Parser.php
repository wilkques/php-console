<?php

namespace Wilkques\Console;

class Parser
{
    /**
     * command parser
     * 
     * @param array $tokens
     * 
     * @return array
     */
    public static function parser($tokens)
    {
        $options = array();

        $arguments = array();

        $endOfOptions = false;

        foreach ($tokens as $arg) {
            // C4 — a bare "--" ends option parsing: every token after it
            // is a positional argument even if it looks like an option
            // (starts with "-"). The "--" token itself is consumed, not
            // kept as an argument.
            if (!$endOfOptions && $arg === '--') {
                $endOfOptions = true;

                continue;
            }

            if (!$endOfOptions && strpos($arg, '-') === 0) {
                // Strip the leading dashes: "--debug=false" -> "debug=false",
                // "-v" -> "v". The previous regex-based approach left
                // single-dash short options with their dash still attached
                // (so option('v') could never find "-v"), and raised an
                // "Undefined array key 1" warning whenever the token held
                // anything the pattern matched a second time, such as a
                // quoted value containing spaces.
                $key = ltrim($arg, '-');

                $value = 'true';

                if (strpos($key, '=') !== false) {
                    list($key, $value) = explode('=', $key, 2);
                }

                $options[$key] = is_a_to($value);
            } else {
                $arguments[] = $arg;
            }
        }

        return compact('options', 'arguments');
    }
}