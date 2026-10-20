<?php

namespace HiEvents\Enterprise\Sso\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SsoProviderType;

class LinkableSsoConnection extends BaseDataObject
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly SsoProviderType $type,
        public readonly string $accountName,
    ) {}
}
