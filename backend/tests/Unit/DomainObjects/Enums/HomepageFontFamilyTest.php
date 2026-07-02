<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use Tests\TestCase;

class HomepageFontFamilyTest extends TestCase
{
    public function test_values_array_contains_curated_fonts(): void
    {
        $values = HomepageFontFamily::valuesArray();

        $this->assertContains('Outfit', $values);
        $this->assertContains('Inter', $values);
        $this->assertContains('Plus Jakarta Sans', $values);
        $this->assertContains('Playfair Display', $values);
        $this->assertContains('Bebas Neue', $values);
    }

    public function test_values_are_unique_non_empty_strings(): void
    {
        $values = HomepageFontFamily::valuesArray();

        $this->assertNotEmpty($values);
        $this->assertSame($values, array_values(array_unique($values)));

        foreach ($values as $value) {
            $this->assertIsString($value);
            $this->assertNotSame('', trim($value));
        }
    }
}
