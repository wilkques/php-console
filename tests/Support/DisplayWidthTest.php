<?php

namespace Wilkques\Console\Tests\Support;

use Wilkques\Console\Support\DisplayWidth;
use Wilkques\Console\Tests\TestCase;

class DisplayWidthTest extends TestCase
{
    public function test_ascii_width_equals_its_length()
    {
        $this->assertSame(5, DisplayWidth::width('hello'));
    }

    public function test_empty_string_has_zero_width()
    {
        $this->assertSame(0, DisplayWidth::width(''));
    }

    public function test_cjk_characters_count_as_two_columns_each()
    {
        // "鮑伯" is 2 codepoints, 6 UTF-8 bytes, but 4 display columns.
        $this->assertSame(4, DisplayWidth::width('鮑伯'));
    }

    public function test_mixed_ascii_and_cjk_width()
    {
        // "a" (1) + "鮑伯" (4) + "b" (1) = 6, even though strlen() would
        // report 8 bytes.
        $this->assertSame(6, DisplayWidth::width('a鮑伯b'));
        $this->assertNotSame(DisplayWidth::width('a鮑伯b'), strlen('a鮑伯b'));
    }

    public function test_pad_pads_ascii_by_byte_length_same_as_str_pad()
    {
        $this->assertSame('ab   ', DisplayWidth::pad('ab', 5));
    }

    public function test_pad_pads_cjk_by_display_width_not_byte_length()
    {
        // "鮑伯" is display-width 4; padding to 6 should add 2 spaces,
        // not (6 - strlen()) spaces.
        $this->assertSame('鮑伯  ', DisplayWidth::pad('鮑伯', 6));
    }

    public function test_pad_returns_the_string_unchanged_when_already_at_or_over_width()
    {
        $this->assertSame('鮑伯', DisplayWidth::pad('鮑伯', 4));
        $this->assertSame('鮑伯', DisplayWidth::pad('鮑伯', 2));
    }

    public function test_pad_left()
    {
        $this->assertSame('  鮑伯', DisplayWidth::pad('鮑伯', 6, STR_PAD_LEFT));
    }
}
