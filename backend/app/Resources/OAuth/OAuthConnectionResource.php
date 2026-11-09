<?php

namespace HiEvents\Resources\OAuth;

use HiEvents\DomainObjects\OauthConnectionDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use Illuminate\Http\Request;

/**
 * @mixin OauthConnectionDomainObject
 */
class OAuthConnectionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $policy = app(OAuthRedirectUriPolicy::class);
        $redirectUris = $this->getOauthClient()?->getRedirectUris() ?? [];

        return [
            'id' => $this->getId(),
            'app_name' => $this->getOauthClient()?->getName(),
            'redirect_host' => $redirectUris === [] ? null : $policy->describe($redirectUris[0]),
            'is_verified' => $policy->isVerified($redirectUris),
            /** @var 'READ_ONLY'|'READ_WRITE' */
            'access' => $this->getAccess(),
            'last_used_at' => $this->getLastUsedAt(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
