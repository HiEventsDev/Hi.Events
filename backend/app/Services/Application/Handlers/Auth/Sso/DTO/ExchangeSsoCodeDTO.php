<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ExchangeSsoCodeDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $code,
        public readonly string $state,
        public readonly string $binding,
        public readonly ?string $trustedDeviceToken = null,
        public readonly ?int $authenticatedUserId = null,
        public readonly ?string $tokenId = null,
    ) {}
}
