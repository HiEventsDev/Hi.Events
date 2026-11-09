<?php

namespace HiEvents\Services\Application\Handlers\OAuth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class OAuthConsentDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $request_id,
        public readonly string $client_name,
        public readonly string $redirect_host,
        public readonly bool $is_verified,
        public readonly bool $is_local,
        public readonly ApiKeyAccess $requested_access,
        public readonly string $account_name,
    ) {}
}
