<?php

namespace HiEvents\Services\Domain\Auth\OAuth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OAuthTokensDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $access_token,
        public readonly string $refresh_token,
        public readonly int $expires_in,
        public readonly string $scope,
    ) {}

    public function toTokenResponse(): array
    {
        return [
            'access_token' => $this->access_token,
            'token_type' => 'Bearer',
            'expires_in' => $this->expires_in,
            'refresh_token' => $this->refresh_token,
            'scope' => $this->scope,
        ];
    }
}
