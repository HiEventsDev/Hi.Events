<?php

namespace HiEvents\Services\Application\Handlers\OAuth;

use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\DomainObjects\Enums\OAuthScope;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationRequestDTO;
use HiEvents\Services\Domain\Auth\OAuth\OAuthAuthorizationStore;
use HiEvents\Services\Domain\Auth\OAuth\OAuthEndpoints;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;

class StartOAuthAuthorizationHandler
{
    private const CODE_CHALLENGE_PATTERN = '/^[A-Za-z0-9\-._~]{43,128}$/';

    public function __construct(
        private readonly OauthClientRepositoryInterface $clientRepository,
        private readonly OAuthRedirectUriPolicy $redirectUriPolicy,
        private readonly OAuthAuthorizationStore $store,
        private readonly OAuthEndpoints $endpoints,
    ) {}

    public function handle(array $query): string
    {
        $clientId = is_string($query['client_id'] ?? null) ? $query['client_id'] : '';
        $redirectUri = is_string($query['redirect_uri'] ?? null) ? $query['redirect_uri'] : '';
        $state = is_string($query['state'] ?? null) ? $query['state'] : null;

        /** @var OauthClientDomainObject|null $client */
        $client = $clientId === '' ? null : $this->clientRepository->findFirstWhere(['client_id' => $clientId]);

        if ($client === null) {
            return $this->endpoints->consentUrl(['error' => OAuthErrorCode::INVALID_CLIENT->value]);
        }

        if ($redirectUri === '' || ! $this->redirectUriPolicy->matches($client->getRedirectUris() ?? [], $redirectUri)) {
            return $this->endpoints->consentUrl(['error' => OAuthErrorCode::INVALID_REDIRECT_URI->value]);
        }

        $error = match (true) {
            ($query['response_type'] ?? null) !== 'code' => OAuthErrorCode::UNSUPPORTED_RESPONSE_TYPE,
            ($query['code_challenge_method'] ?? null) !== 'S256',
            ! is_string($query['code_challenge'] ?? null),
            preg_match(self::CODE_CHALLENGE_PATTERN, (string) ($query['code_challenge'] ?? '')) !== 1 => OAuthErrorCode::INVALID_REQUEST,
            ! $this->endpoints->isResource(is_string($query['resource'] ?? null) ? $query['resource'] : null) => OAuthErrorCode::INVALID_TARGET,
            default => null,
        };

        if ($error !== null) {
            return $this->endpoints->clientRedirect($redirectUri, ['error' => $error->value, 'state' => $state]);
        }

        $requestId = $this->store->storeRequest(new AuthorizationRequestDTO(
            client_id: $client->getClientId(),
            redirect_uri: $redirectUri,
            code_challenge: $query['code_challenge'],
            access: OAuthScope::accessFor(is_string($query['scope'] ?? null) ? $query['scope'] : null),
            state: $state,
            resource: is_string($query['resource'] ?? null) ? $query['resource'] : null,
        ));

        $this->clientRepository->updateWhere(['last_used_at' => now()], ['id' => $client->getId()]);

        return $this->endpoints->consentUrl(['request' => $requestId]);
    }
}
