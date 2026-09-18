<?php

namespace Wilkques\Console\Tests\Support;

use Wilkques\Console\Support\TableFormatter;
use Wilkques\Console\Tests\TestCase;

class TableFormatterTest extends TestCase
{
    public function test_basic_table_with_padded_columns()
    {
        $expected = '+------+-----+' . PHP_EOL
            . '| Name | Age |' . PHP_EOL
            . '+------+-----+' . PHP_EOL
            . '| Bob  | 30  |' . PHP_EOL
            . '| Ann  | 25  |' . PHP_EOL
            . '+------+-----+' . PHP_EOL;

        $actual = TableFormatter::format(
            array('Name', 'Age'),
            array(
                array('Bob', 30),
                array('Ann', 25),
            )
        );

        $this->assertSame($expected, $actual);
    }

    public function test_column_width_is_driven_by_the_widest_cell_not_the_header()
    {
        $expected = '+----------------+' . PHP_EOL
            . '| Name           |' . PHP_EOL
            . '+----------------+' . PHP_EOL
            . '| Alexandra-Rose |' . PHP_EOL
            . '+----------------+' . PHP_EOL;

        $actual = TableFormatter::format(
            array('Name'),
            array(
                array('Alexandra-Rose'),
            )
        );

        $this->assertSame($expected, $actual);
    }

    public function test_headers_only_with_no_rows_has_no_duplicate_trailing_separator()
    {
        $expected = '+------+' . PHP_EOL
            . '| Name |' . PHP_EOL
            . '+------+' . PHP_EOL;

        $actual = TableFormatter::format(array('Name'), array());

        $this->assertSame($expected, $actual);
    }

    public function test_a_short_row_pads_its_missing_trailing_cells_blank()
    {
        $expected = '+------+-----+' . PHP_EOL
            . '| Name | Age |' . PHP_EOL
            . '+------+-----+' . PHP_EOL
            . '| Bob  |     |' . PHP_EOL
            . '+------+-----+' . PHP_EOL;

        $actual = TableFormatter::format(
            array('Name', 'Age'),
            array(
                array('Bob'),
            )
        );

        $this->assertSame($expected, $actual);
    }

    public function test_cjk_cell_content_is_padded_by_display_width_not_byte_length()
    {
        // '鮑伯' is 2 codepoints / 6 UTF-8 bytes but only 4 display
        // columns — str_pad()'s byte-based padding would misalign this
        // column against 'Alice' (5 bytes, 5 columns).
        $expected = '+-------+-------+' . PHP_EOL
            . '| Name  | Score |' . PHP_EOL
            . '+-------+-------+' . PHP_EOL
            . '| Alice | 95    |' . PHP_EOL
            . '| 鮑伯  | 30    |' . PHP_EOL
            . '+-------+-------+' . PHP_EOL;

        $actual = TableFormatter::format(
            array('Name', 'Score'),
            array(
                array('Alice', 95),
                array('鮑伯', 30),
            )
        );

        $this->assertSame($expected, $actual);
    }

    public function test_associative_row_arrays_are_read_in_value_order()
    {
        $expected = '+------+-----+' . PHP_EOL
            . '| Name | Age |' . PHP_EOL
            . '+------+-----+' . PHP_EOL
            . '| Bob  | 30  |' . PHP_EOL
            . '+------+-----+' . PHP_EOL;

        $actual = TableFormatter::format(
            array('Name', 'Age'),
            array(
                array('name' => 'Bob', 'age' => 30),
            )
        );

        $this->assertSame($expected, $actual);
    }
}
