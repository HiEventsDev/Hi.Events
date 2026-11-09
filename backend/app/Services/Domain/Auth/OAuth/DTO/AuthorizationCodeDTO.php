<?php

namespace HiEvents\Services\Domain\Auth\OAuth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class AuthorizationCodeDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $client_id,
        public readonly string $redirect_uri,
        public readonly string $code_challenge,
        public readonly int $user_id,
        public readonly int $account_id,
        public readonly ApiKeyAccess $access,
        public readonly ?string $resource = null,
    ) {}
}
