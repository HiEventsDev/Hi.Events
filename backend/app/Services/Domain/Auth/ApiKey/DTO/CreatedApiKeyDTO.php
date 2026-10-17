<?php

namespace HiEvents\Services\Domain\Auth\ApiKey\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;

class CreatedApiKeyDTO extends BaseDataObject
{
    public function __construct(
        public readonly PersonalAccessTokenDomainObject $apiKey,
        public readonly string $plainTextKey,
    ) {}
}
