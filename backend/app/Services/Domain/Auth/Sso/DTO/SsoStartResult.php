<?php

namespace HiEvents\Services\Domain\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SsoStartResult extends BaseDataObject
{
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $binding,
    ) {}
}
