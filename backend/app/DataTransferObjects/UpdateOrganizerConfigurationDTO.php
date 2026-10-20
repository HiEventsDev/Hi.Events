<?php

namespace HiEvents\DataTransferObjects;

class UpdateOrganizerConfigurationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizerId,
        public readonly array $applicationFees,
    ) {}
}
