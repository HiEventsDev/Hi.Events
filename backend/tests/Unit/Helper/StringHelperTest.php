<?php

namespace Tests\Unit\Helper;

use HiEvents\Helper\StringHelper;
use Tests\TestCase;

class StringHelperTest extends TestCase
{
    public function test_strip_control_characters_removes_control_and_separator_characters(): void
    {
        $input = "Hello\x00\x1F\x7F\u{2028}\u{2029}World";

        $this->assertSame('HelloWorld', StringHelper::stripControlCharacters($input));
    }

    public function test_strip_control_characters_preserves_legitimate_printable_characters(): void
    {
        $input = 'Rock & Roll: a < b > c "Live" 🎸';

        $this->assertSame($input, StringHelper::stripControlCharacters($input));
    }

    public function test_strip_control_characters_preserves_script_like_text(): void
    {
        // The title is stored faithfully; escaping is the renderer's responsibility.
        $input = '</script><svg onload=alert(1)>';

        $this->assertSame($input, StringHelper::stripControlCharacters($input));
    }
}
