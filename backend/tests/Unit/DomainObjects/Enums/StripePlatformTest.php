<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\StripePlatform;
use Tests\TestCase;

class StripePlatformTest extends TestCase
{
    public function test_from_string_returns_correct_enum(): void
    {
        $this->assertEquals(StripePlatform::CANADA, StripePlatform::fromString('ca'));
        $this->assertEquals(StripePlatform::IRELAND, StripePlatform::fromString('ie'));
        $this->assertNull(StripePlatform::fromString(null));
        $this->assertNull(StripePlatform::fromString('invalid'));
    }

    public function test_to_string_returns_value(): void
    {
        $this->assertEquals('ca', StripePlatform::CANADA->toString());
        $this->assertEquals('ie', StripePlatform::IRELAND->toString());
    }

    public function test_get_all_values(): void
    {
        $expected = ['ca', 'ie'];
        $this->assertEquals($expected, StripePlatform::getAllValues());
    }
}