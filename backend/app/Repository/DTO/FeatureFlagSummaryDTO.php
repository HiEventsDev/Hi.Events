<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class FeatureFlagSummaryDTO extends BaseDataObject
{
    public function __construct(
        public string $key,
        public bool $enabledByDefault,
        public int $enabledOverrideCount,
        public int $disabledOverrideCount,
    ) {}
}
