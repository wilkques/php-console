<?php

namespace Wilkques\Console\Support;

/**
 * How many terminal columns a UTF-8 string actually occupies, and
 * str_pad()'s equivalent measured in that instead of bytes.
 *
 * strlen()/str_pad() count BYTES, and a multi-byte UTF-8 character is
 * several bytes wide but renders in either 1 or 2 terminal columns —
 * never 3+, regardless of encoded byte length. TableFormatter and
 * HelperListFormatter both align columns by padding to a shared width;
 * using strlen()/str_pad() for that misaligns any row containing CJK
 * text (Chinese/Japanese/Korean), fullwidth punctuation, etc., because
 * the byte count and the actual rendered column count diverge.
 *
 * Decodes UTF-8 by hand (no ext-mbstring dependency, since this package's
 * floor is PHP 5.3 and mbstring isn't guaranteed to be installed there)
 * and classifies each codepoint as 1 or 2 columns wide using the
 * standard East-Asian-Wide/Fullwidth Unicode ranges. Combining marks and
 * other zero-width codepoints aren't accounted for — everything not
 * recognized as wide counts as exactly 1 column — which covers the
 * common CJK-misalignment case this exists to fix without pulling in a
 * full Unicode width table.
 */
class DisplayWidth
{
    /**
     * @param string $string
     *
     * @return int
     */
    public static function width($string)
    {
        $width = 0;

        foreach (static::codepoints($string) as $codepoint) {
            $width += static::isWide($codepoint) ? 2 : 1;
        }

        return $width;
    }

    /**
     * str_pad(), but measuring (and padding to) display width instead of
     * byte length.
     *
     * @param string $string
     * @param int    $targetWidth
     * @param int    $padType STR_PAD_RIGHT (default) or STR_PAD_LEFT
     *
     * @return string
     */
    public static function pad($string, $targetWidth, $padType = STR_PAD_RIGHT)
    {
        $padLength = $targetWidth - static::width($string);

        if ($padLength <= 0) {
            return $string;
        }

        $padding = str_repeat(' ', $padLength);

        if ($padType === STR_PAD_LEFT) {
            return $padding . $string;
        }

        return $string . $padding;
    }

    /**
     * Decode a UTF-8 byte string into an array of Unicode codepoints.
     * Malformed sequences are treated as a single width-1 byte each
     * rather than raising an error, so a non-UTF-8 string still renders
     * (just not necessarily aligned) instead of breaking table output.
     *
     * @param string $string
     *
     * @return int[]
     */
    protected static function codepoints($string)
    {
        $codepoints = array();

        $length = strlen($string);

        $i = 0;

        while ($i < $length) {
            $byte = ord($string[$i]);

            if ($byte < 0x80) {
                $codepoints[] = $byte;

                $i += 1;
            } elseif (($byte & 0xE0) === 0xC0 && $i + 1 < $length) {
                $codepoints[] = (($byte & 0x1F) << 6)
                    | (ord($string[$i + 1]) & 0x3F);

                $i += 2;
            } elseif (($byte & 0xF0) === 0xE0 && $i + 2 < $length) {
                $codepoints[] = (($byte & 0x0F) << 12)
                    | ((ord($string[$i + 1]) & 0x3F) << 6)
                    | (ord($string[$i + 2]) & 0x3F);

                $i += 3;
            } elseif (($byte & 0xF8) === 0xF0 && $i + 3 < $length) {
                $codepoints[] = (($byte & 0x07) << 18)
                    | ((ord($string[$i + 1]) & 0x3F) << 12)
                    | ((ord($string[$i + 2]) & 0x3F) << 6)
                    | (ord($string[$i + 3]) & 0x3F);

                $i += 4;
            } else {
                $codepoints[] = $byte;

                $i += 1;
            }
        }

        return $codepoints;
    }

    /**
     * Standard East-Asian-Wide / Fullwidth Unicode ranges: CJK ideographs
     * and radicals, hiragana/katakana, Hangul (jamo and syllables),
     * fullwidth forms/punctuation, and the CJK supplementary planes.
     *
     * @param int $codepoint
     *
     * @return bool
     */
    protected static function isWide($codepoint)
    {
        static $ranges = array(
            array(0x1100, 0x115F),
            array(0x2E80, 0x303E),
            array(0x3041, 0x33FF),
            array(0x3400, 0x4DBF),
            array(0x4E00, 0x9FFF),
            array(0xA000, 0xA4CF),
            array(0xAC00, 0xD7A3),
            array(0xF900, 0xFAFF),
            array(0xFE30, 0xFE4F),
            array(0xFF00, 0xFF60),
            array(0xFFE0, 0xFFE6),
            array(0x20000, 0x2FFFD),
            array(0x30000, 0x3FFFD),
        );

        foreach ($ranges as $range) {
            if ($codepoint >= $range[0] && $codepoint <= $range[1]) {
                return true;
            }
        }

        return false;
    }
}
