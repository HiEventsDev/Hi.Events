<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;

class SsoIdentityClaimsMapper
{
    public const MICROSOFT_CONSUMER_TENANT_ID = '9188040d-6c67-4c5b-b112-36a304b66dad';

    private const MAX_NAME_LENGTH = 50;

    private const MAX_GROUPS = 50;

    /**
     * @throws SsoException
     */
    public function map(SsoProviderConfig $provider, array $claims): SsoIdentityClaims
    {
        $email = $this->email($claims);
        [$firstName, $lastName] = $this->names($claims);

        return new SsoIdentityClaims(
            providerKey: $provider->key,
            issuer: $this->issuer($provider, $claims),
            subject: $this->subject($provider, $claims),
            email: $email,
            emailTrusted: $email !== null && $this->isEmailTrusted($provider, $claims, $email),
            firstName: $firstName,
            lastName: $lastName,
            authTime: is_numeric($claims['auth_time'] ?? null) ? (int) $claims['auth_time'] : null,
            connectionId: $provider->connectionId,
            groups: $this->groups($claims),
        );
    }

    private function issuer(SsoProviderConfig $provider, array $claims): string
    {
        return match ($provider->type) {
            SsoProviderType::GOOGLE => 'https://accounts.google.com',
            SsoProviderType::MICROSOFT => (string) $claims['iss'],
            SsoProviderType::OIDC => $provider->issuer,
        };
    }

    /**
     * @throws SsoException
     */
    private function subject(SsoProviderConfig $provider, array $claims): string
    {
        $subject = $provider->type === SsoProviderType::MICROSOFT
            ? ($claims['oid'] ?? null)
            : ($claims['sub'] ?? null);

        if (! is_string($subject) || $subject === '') {
            throw new SsoException(SsoErrorCode::TOKEN_INVALID, 'Token is missing the subject identifier');
        }

        return $subject;
    }

    private function email(array $claims): ?string
    {
        $email = is_string($claims['email'] ?? null) ? strtolower(trim($claims['email'])) : null;

        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function isEmailTrusted(SsoProviderConfig $provider, array $claims, string $email): bool
    {
        if ($provider->isAccountConnection()) {
            return $provider->type === SsoProviderType::MICROSOFT
                ? $this->isTrue($claims['xms_edov'] ?? null)
                : $this->isTrue($claims['email_verified'] ?? null);
        }

        return match ($provider->type) {
            SsoProviderType::GOOGLE => $this->isTrue($claims['email_verified'] ?? null)
                && (str_ends_with($email, '@gmail.com') || is_string($claims['hd'] ?? null)),
            SsoProviderType::MICROSOFT => $this->isTrue($claims['xms_edov'] ?? null)
                && ($claims['tid'] ?? null) !== self::MICROSOFT_CONSUMER_TENANT_ID,
            SsoProviderType::OIDC => $provider->trustEmail && $this->isTrue($claims['email_verified'] ?? null),
        };
    }

    private function isTrue(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function names(array $claims): array
    {
        $firstName = $this->cleanName($claims['given_name'] ?? null);
        $lastName = $this->cleanName($claims['family_name'] ?? null);

        if ($firstName === null && is_string($claims['name'] ?? null)) {
            $parts = preg_split('/\s+/', trim($claims['name']), 2) ?: [];
            $firstName = $this->cleanName($parts[0] ?? null);
            $lastName ??= $this->cleanName($parts[1] ?? null);
        }

        return [$firstName, $lastName];
    }

    /**
     * @return string[]
     */
    private function groups(array $claims): array
    {
        if (! is_array($claims['groups'] ?? null)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            array_map(fn (mixed $group) => is_string($group) ? mb_substr(trim($group), 0, 200) : null, $claims['groups']),
            fn (?string $group) => $group !== null && $group !== '',
        )), 0, self::MAX_GROUPS);
    }

    private function cleanName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_NAME_LENGTH);
    }
}
