<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AccountFeatureFlagDTO extends BaseDataObject
{
    public function __construct(
        public string $key,
        public bool $enabledByDefault,
        public ?bool $override,
    ) {}

    public function isEnabled(): bool
    {
        return $this->override ?? $this->enabledByDefault;
    }
}
