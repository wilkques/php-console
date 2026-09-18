<?php

namespace Wilkques\Console\Support;

/**
 * Formats a header + rows into an ASCII box-drawn table, e.g.:
 *
 *   +------+-----+
 *   | Name | Age |
 *   +------+-----+
 *   | Bob  | 30  |
 *   | Ann  | 25  |
 *   +------+-----+
 *
 * A pure formatter — takes headers/rows, returns a string, does no I/O
 * itself — so it's testable directly without capturing stdout (see
 * tests/Support/TableFormatterTest.php). Command::table() just echoes
 * what this returns. Same idea as HelperListFormatter, for the same
 * reason: keep formatting logic independently testable from the command
 * that prints it.
 *
 * Column width is measured with DisplayWidth (terminal columns), not
 * strlen() (bytes), so CJK/fullwidth cell content still lines up.
 */
class TableFormatter
{
    /**
     * @param array $headers
     * @param array $rows    array of arrays, one per row; a row's own
     *                       array keys are ignored, only value order
     *                       matters (array_values() is applied)
     *
     * @return string
     */
    public static function format(array $headers, array $rows)
    {
        $widths = static::columnWidths($headers, $rows);

        $separator = static::separatorLine($widths);

        $lines = array();

        $lines[] = $separator;
        $lines[] = static::rowLine($headers, $widths);
        $lines[] = $separator;

        foreach ($rows as $row) {
            $lines[] = static::rowLine($row, $widths);
        }

        if ($rows) {
            $lines[] = $separator;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array $headers
     * @param array $rows
     *
     * @return array column index => width
     */
    protected static function columnWidths($headers, $rows)
    {
        $widths = array();

        foreach (array_values($headers) as $index => $header) {
            $widths[$index] = DisplayWidth::width((string) $header);
        }

        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $cell) {
                $width = DisplayWidth::width((string) $cell);

                if (!isset($widths[$index]) || $width > $widths[$index]) {
                    $widths[$index] = $width;
                }
            }
        }

        return $widths;
    }

    /**
     * @param array $widths column index => width
     *
     * @return string
     */
    protected static function separatorLine($widths)
    {
        $segments = array();

        foreach ($widths as $width) {
            $segments[] = str_repeat('-', $width + 2);
        }

        return '+' . implode('+', $segments) . '+';
    }

    /**
     * @param array $row
     * @param array $widths column index => width
     *
     * @return string
     */
    protected static function rowLine($row, $widths)
    {
        $row = array_values($row);

        $cells = array();

        foreach ($widths as $index => $width) {
            $value = isset($row[$index]) ? (string) $row[$index] : '';

            $cells[] = ' ' . DisplayWidth::pad($value, $width) . ' ';
        }

        return '|' . implode('|', $cells) . '|';
    }
}
