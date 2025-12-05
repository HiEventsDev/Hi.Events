<?php

namespace HiEvents\DataTransferObjects;

class UpdateAccountConfigurationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly array $applicationFees,
    )
    {
    }
}
