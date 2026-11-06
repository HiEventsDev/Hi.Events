<?php

namespace HiEvents\Services\Domain\Auth\OAuth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class AuthorizationRequestDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $client_id,
        public readonly string $redirect_uri,
        public readonly string $code_challenge,
        public readonly ApiKeyAccess $access,
        public readonly ?string $state = null,
        public readonly ?string $resource = null,
    ) {}
}
