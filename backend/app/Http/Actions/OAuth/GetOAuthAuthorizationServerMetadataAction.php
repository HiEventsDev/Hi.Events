<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\OAuth\OAuthEndpoints;
use Illuminate\Http\JsonResponse;

class GetOAuthAuthorizationServerMetadataAction extends BaseAction
{
    public function __construct(
        private readonly OAuthEndpoints $endpoints,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse($this->endpoints->authorizationServerMetadata());
    }
}
