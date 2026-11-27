<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmSegmentPresetTest extends TestCase
{
    public static function presets(): array
    {
        $presets = [];
        foreach (CrmSegmentPreset::cases() as $preset) {
            $presets[$preset->value] = [$preset];
        }

        return $presets;
    }

    #[DataProvider('presets')]
    public function test_a_preset_is_already_a_normalized_definition_that_needs_no_financial_access(CrmSegmentPreset $preset): void
    {
        $normalized = (new SegmentDefinitionNormalizer)->normalize($preset->definition(), false);

        $this->assertSame($preset->definition(), $normalized);
        $this->assertNotSame([], $normalized['rules']);
    }
}
