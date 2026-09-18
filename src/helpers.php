<?php

if (!function_exists('console')) {
    /**
     * @return \Wilkques\Console\Console
     */
    function console()
    {
        return \Wilkques\Console\Console::make();
    }
}

if (!function_exists('is_a_to')) {
    /**
     * @param string $value
     * 
     * @return string|bool
     */
    function is_a_to($value, $callback = null)
    {
        if (in_array($value, array('false', 'true'))) {
            return $value == "true" ? true : ($value == "false" ? false : false);
        }

        $callback && $value = $callback($value);

        return $value;
    }
}
