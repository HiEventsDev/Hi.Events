<?php

namespace Tests\Concerns;

use HiEvents\Models\AccountSsoConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait CreatesSsoConnections
{
    use FakesOidcProvider;

    protected const CONNECTION_ISSUER = 'https://acme.okta.test';

    protected const CONNECTION_CLIENT_ID = 'acme-client';

    protected function fakeConnectionIssuer(string $issuer = self::CONNECTION_ISSUER): void
    {
        $this->fakeDns();

        Http::fake([
            $issuer.'/.well-known/openid-configuration' => Http::response([
                'issuer' => $issuer,
                'authorization_endpoint' => $issuer.'/authorize',
                'token_endpoint' => $issuer.'/token',
                'jwks_uri' => $issuer.'/jwks',
                'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            ]),
            $issuer.'/jwks' => Http::response(['keys' => [$this->oidcPublicJwk()]]),
            $issuer.'/token' => function (Request $request) {
                $this->lastTokenRequest = $request->data();

                return Http::response([
                    'access_token' => 'access-token',
                    'token_type' => 'Bearer',
                    'id_token' => $this->signOidcToken($this->oidcClaims),
                ]);
            },
        ]);
    }

    protected function createConnection(int $accountId, array $attributes = []): AccountSsoConnection
    {
        return AccountSsoConnection::create([
            'account_id' => $accountId,
            'type' => 'OKTA',
            'issuer' => self::CONNECTION_ISSUER,
            'client_id' => self::CONNECTION_CLIENT_ID,
            'client_secret' => 'connection-secret',
            'status' => 'ACTIVE',
            'session_ttl_minutes' => 720,
            'jit_provisioning_enabled' => false,
            'jit_default_role' => 'ORGANIZER',
            'last_tested_at' => now(),
            ...$attributes,
        ]);
    }

    protected function addDomain(int $accountId, string $domain, bool $verified = true, ?string $failingSince = null): int
    {
        return DB::table('account_sso_domains')->insertGetId([
            'account_id' => $accountId,
            'domain' => $domain,
            'verification_token' => 'token-'.$domain,
            'verified_at' => $verified ? now() : null,
            'failing_since' => $failingSince,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function connectionClaims(string $subject, ?string $email, array $extra = []): array
    {
        return [
            'iss' => self::CONNECTION_ISSUER,
            'aud' => self::CONNECTION_CLIENT_ID,
            'sub' => $subject,
            'email' => $email,
            'email_verified' => true,
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
            ...$extra,
        ];
    }

    protected function startConnectionFlow(AccountSsoConnection $connection, array $payload = ['intent' => 'LOGIN']): array
    {
        return $this->startOidcFlow($payload, 'connection-'.$connection->id);
    }

    protected function bearer(string $token): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$token];
    }

    protected function decodeToken(string $token): array
    {
        $this->app['tymon.jwt']->unsetToken();

        return JWTAuth::setToken($token)->getPayload()->toArray();
    }
}
