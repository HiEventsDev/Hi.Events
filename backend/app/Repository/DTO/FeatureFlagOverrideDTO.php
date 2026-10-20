<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class FeatureFlagOverrideDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public string $accountName,
        public bool $enabled,
        public string $updatedAt,
    ) {}
}
