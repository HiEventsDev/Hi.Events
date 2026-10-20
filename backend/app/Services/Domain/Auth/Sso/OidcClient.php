<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Helper\Url;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;

class OidcClient
{
    private const SCOPES = 'openid email profile';

    public function __construct(
        private readonly OidcDiscoveryClient $discoveryClient,
        private readonly IdTokenValidator $idTokenValidator,
        private readonly SsoIdentityClaimsMapper $claimsMapper,
    ) {}

    /**
     * @throws SsoException
     */
    public function authorizationUrl(
        SsoProviderConfig $provider,
        SsoIntent $intent,
        string $state,
        string $nonce,
        string $codeVerifier,
        bool $switchAccount = false,
        ?string $loginHint = null,
        bool $silent = false,
    ): string {
        $endpoint = $this->discoveryClient->discover($provider)['authorization_endpoint'];

        $params = [
            'response_type' => 'code',
            'client_id' => $provider->clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPES,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ];

        if ($silent) {
            $params['prompt'] = 'none';
        } elseif ($intent === SsoIntent::REAUTH) {
            $params['prompt'] = 'login';
            $params['max_age'] = 0;
        } elseif ($intent === SsoIntent::TEST) {
            $params['prompt'] = 'login';
        } elseif ($switchAccount || ($provider->type !== SsoProviderType::OIDC && ! $provider->isAccountConnection())) {
            $params['prompt'] = 'select_account';
        }

        if ($loginHint !== null && ! $switchAccount) {
            $params['login_hint'] = $loginHint;
        }

        if ($provider->hostedDomain !== null) {
            $params['hd'] = $provider->hostedDomain;
        }

        if ($provider->type === SsoProviderType::MICROSOFT) {
            $params['claims'] = json_encode(['id_token' => ['xms_edov' => ['essential' => false]]]);
        }

        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint.$separator.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @throws SsoException
     */
    public function exchange(SsoProviderConfig $provider, string $code, string $codeVerifier, string $nonce): SsoIdentityClaims
    {
        $discovery = $this->discoveryClient->discover($provider);
        $useBasicAuth = $this->prefersBasicAuth($discovery);

        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ];

        if (! $useBasicAuth) {
            $form['client_id'] = $provider->clientId;
            $form['client_secret'] = $provider->clientSecret;
        }

        $response = $this->discoveryClient->post(
            provider: $provider,
            url: $discovery['token_endpoint'],
            form: $form,
            basicAuth: $useBasicAuth ? [rawurlencode($provider->clientId), rawurlencode($provider->clientSecret)] : null,
        );

        $idToken = $response['id_token'] ?? null;

        if (! is_string($idToken) || $idToken === '') {
            throw new SsoException(SsoErrorCode::TOKEN_INVALID, 'Token response did not include an ID token');
        }

        $claims = $this->idTokenValidator->validate(
            provider: $provider,
            idToken: $idToken,
            expectedNonce: $nonce,
            accessToken: is_string($response['access_token'] ?? null) ? $response['access_token'] : null,
        );

        return $this->claimsMapper->map($provider, $claims);
    }

    public function redirectUri(): string
    {
        return Url::getFrontEndUrlFromConfig(Url::SSO_CALLBACK);
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    private function prefersBasicAuth(array $discovery): bool
    {
        $methods = $discovery['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'];

        return ! is_array($methods) || in_array('client_secret_basic', $methods, true) || ! in_array('client_secret_post', $methods, true);
    }
}
