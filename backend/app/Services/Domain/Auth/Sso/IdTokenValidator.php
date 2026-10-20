<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use DateInterval;
use DateTimeInterface;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\Jwk\JwkConverter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Ecdsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;

class IdTokenValidator
{
    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    private const LEEWAY_SECONDS = 60;

    private const ALGORITHMS = [
        'RS256' => ['kty' => 'RSA', 'crv' => null],
        'RS384' => ['kty' => 'RSA', 'crv' => null],
        'RS512' => ['kty' => 'RSA', 'crv' => null],
        'ES256' => ['kty' => 'EC', 'crv' => 'P-256'],
        'ES384' => ['kty' => 'EC', 'crv' => 'P-384'],
    ];

    public function __construct(
        private readonly OidcDiscoveryClient $discoveryClient,
        private readonly JwkConverter $jwkConverter,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws SsoException
     */
    public function validate(SsoProviderConfig $provider, string $idToken, string $expectedNonce, ?string $accessToken): array
    {
        $token = $this->parse($idToken);
        $algorithm = $token->headers()->get('alg');
        $keyId = $token->headers()->get('kid');

        if (! is_string($algorithm) || ! isset(self::ALGORITHMS[$algorithm]) || ! is_string($keyId)) {
            throw $this->invalid('Unsupported token algorithm or missing key id');
        }

        $jwk = $this->discoveryClient->findSigningKey($provider, $keyId);

        if ($jwk === null || ! $this->keyMatchesAlgorithm($jwk, $algorithm)) {
            throw $this->invalid('No matching signing key');
        }

        try {
            (new Validator)->assert(
                $token,
                new SignedWith($this->signer($algorithm), InMemory::plainText($this->jwkConverter->toPem($jwk))),
                new PermittedFor($provider->clientId),
                new LooseValidAt($this->clock, new DateInterval('PT'.self::LEEWAY_SECONDS.'S')),
            );
        } catch (JwtException $exception) {
            throw $this->invalid($exception->getMessage());
        }

        $claims = $this->normaliseClaims($token->claims()->all());

        $this->assertIssuer($provider, $claims);
        $this->assertConnectionBinding($provider, $claims);
        $this->assertAuthorizedParty($provider, $claims);
        $this->assertNonce($claims, $expectedNonce);
        $this->assertAccessTokenHash($claims, $algorithm, $accessToken);

        if (! isset($claims['exp'], $claims['iat']) || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw $this->invalid('Token is missing required claims');
        }

        return $claims;
    }

    /**
     * @throws SsoException
     */
    private function parse(string $idToken): UnencryptedToken
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($idToken);
        } catch (JwtException $exception) {
            throw $this->invalid($exception->getMessage());
        }

        if (! $token instanceof UnencryptedToken) {
            throw $this->invalid('Encrypted tokens are not supported');
        }

        return $token;
    }

    private function keyMatchesAlgorithm(array $jwk, string $algorithm): bool
    {
        $expected = self::ALGORITHMS[$algorithm];

        if (($jwk['kty'] ?? null) !== $expected['kty']) {
            return false;
        }

        if ($expected['crv'] !== null && ($jwk['crv'] ?? null) !== $expected['crv']) {
            return false;
        }

        return ! isset($jwk['alg']) || $jwk['alg'] === $algorithm;
    }

    private function signer(string $algorithm): Signer
    {
        return match ($algorithm) {
            'RS256' => new Rsa\Sha256,
            'RS384' => new Rsa\Sha384,
            'RS512' => new Rsa\Sha512,
            'ES256' => new Ecdsa\Sha256,
            'ES384' => new Ecdsa\Sha384,
        };
    }

    private function normaliseClaims(array $claims): array
    {
        return array_map(
            static fn (mixed $value) => $value instanceof DateTimeInterface ? $value->getTimestamp() : $value,
            $claims,
        );
    }

    /**
     * @throws SsoException
     */
    private function assertIssuer(SsoProviderConfig $provider, array $claims): void
    {
        $issuer = $claims['iss'] ?? null;

        $valid = match ($provider->type) {
            SsoProviderType::GOOGLE => in_array($issuer, self::GOOGLE_ISSUERS, true),
            SsoProviderType::MICROSOFT => is_string($claims['tid'] ?? null)
                && $claims['tid'] !== ''
                && $issuer === str_replace('{tenantid}', $claims['tid'], OidcDiscoveryClient::MICROSOFT_ISSUER_TEMPLATE),
            SsoProviderType::OIDC => is_string($issuer) && rtrim($issuer, '/') === $provider->issuer,
        };

        if (! $valid) {
            throw $this->invalid('Token issuer does not match');
        }
    }

    /**
     * @throws SsoException
     */
    private function assertConnectionBinding(SsoProviderConfig $provider, array $claims): void
    {
        if ($provider->tenantId !== null && ! $this->claimEquals($claims['tid'] ?? null, $provider->tenantId)) {
            throw $this->invalid('Token tenant does not match the connection');
        }

        if ($provider->hostedDomain !== null && ! $this->claimEquals($claims['hd'] ?? null, $provider->hostedDomain)) {
            throw $this->invalid('Token hosted domain does not match the connection');
        }
    }

    private function claimEquals(mixed $claim, string $expected): bool
    {
        return is_string($claim) && $claim !== '' && strtolower($claim) === strtolower($expected);
    }

    /**
     * @throws SsoException
     */
    private function assertAuthorizedParty(SsoProviderConfig $provider, array $claims): void
    {
        $audience = (array) ($claims['aud'] ?? []);
        $authorizedParty = $claims['azp'] ?? null;

        if (count($audience) > 1 && $authorizedParty !== $provider->clientId) {
            throw $this->invalid('Token authorized party does not match');
        }

        if ($authorizedParty !== null && $authorizedParty !== $provider->clientId) {
            throw $this->invalid('Token authorized party does not match');
        }
    }

    /**
     * @throws SsoException
     */
    private function assertNonce(array $claims, string $expectedNonce): void
    {
        $nonce = $claims['nonce'] ?? null;

        if (! is_string($nonce) || ! hash_equals($expectedNonce, $nonce)) {
            throw $this->invalid('Token nonce does not match');
        }
    }

    /**
     * @throws SsoException
     */
    private function assertAccessTokenHash(array $claims, string $algorithm, ?string $accessToken): void
    {
        if (! isset($claims['at_hash']) || $accessToken === null) {
            return;
        }

        $digest = hash('sha'.substr($algorithm, 2), $accessToken, true);
        $expected = rtrim(strtr(base64_encode(substr($digest, 0, intdiv(strlen($digest), 2))), '+/', '-_'), '=');

        if (! is_string($claims['at_hash']) || ! hash_equals($expected, $claims['at_hash'])) {
            throw $this->invalid('Token access token hash does not match');
        }
    }

    private function invalid(string $reason): SsoException
    {
        return new SsoException(SsoErrorCode::TOKEN_INVALID, $reason);
    }
}
