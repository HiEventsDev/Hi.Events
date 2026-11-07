<?php

namespace HiEvents\Services\Application\Handlers\OAuth;

use HiEvents\DomainObjects\Enums\OAuthClientRegistrationType;
use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\Exceptions\OAuthException;
use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use Illuminate\Support\Str;

class RegisterOAuthClientHandler
{
    public function __construct(
        private readonly OauthClientRepositoryInterface $clientRepository,
        private readonly OAuthRedirectUriPolicy $redirectUriPolicy,
    ) {}

    /**
     * @param  string[]  $redirectUris
     *
     * @throws OAuthException
     */
    public function handle(string $name, array $redirectUris): OauthClientDomainObject
    {
        foreach ($redirectUris as $redirectUri) {
            if (! $this->redirectUriPolicy->isAllowed($redirectUri)) {
                throw new OAuthException(
                    OAuthErrorCode::INVALID_REDIRECT_URI,
                    __('Redirect URIs must use https, a loopback address or an app scheme.'),
                );
            }
        }

        return $this->clientRepository->create([
            'client_id' => 'hiec_'.Str::random(40),
            'name' => $name,
            'redirect_uris' => array_values(array_unique($redirectUris)),
            'registration_type' => OAuthClientRegistrationType::DYNAMIC->value,
        ]);
    }
}
