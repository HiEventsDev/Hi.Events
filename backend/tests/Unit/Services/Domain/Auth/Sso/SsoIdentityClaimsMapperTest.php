<?php

namespace Tests\Unit\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\SsoIdentityClaimsMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SsoIdentityClaimsMapperTest extends TestCase
{
    private const TENANT = '11111111-2222-3333-4444-555555555555';

    #[DataProvider('emailTrust')]
    public function test_decides_whether_an_email_can_be_trusted(SsoProviderType $type, bool $trustEmail, array $claims, bool $trusted): void
    {
        $identity = (new SsoIdentityClaimsMapper)->map($this->provider($type, $trustEmail), [
            'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'sub' => 'subject',
            'oid' => 'object-id',
            ...$claims,
        ]);

        $this->assertSame($trusted, $identity->emailTrusted);
    }

    public static function emailTrust(): array
    {
        return [
            'google gmail' => [SsoProviderType::GOOGLE, true, ['email' => 'a@gmail.com', 'email_verified' => true], true],
            'google workspace' => [SsoProviderType::GOOGLE, true, ['email' => 'a@acme.com', 'email_verified' => true, 'hd' => 'acme.com'], true],
            'google other domain without hd' => [SsoProviderType::GOOGLE, true, ['email' => 'a@acme.com', 'email_verified' => true], false],
            'google unverified' => [SsoProviderType::GOOGLE, true, ['email' => 'a@gmail.com', 'email_verified' => false], false],
            'microsoft with verified domain' => [SsoProviderType::MICROSOFT, true, ['email' => 'a@acme.com', 'xms_edov' => true, 'tid' => self::TENANT], true],
            'microsoft without verified domain' => [SsoProviderType::MICROSOFT, true, ['email' => 'a@acme.com', 'tid' => self::TENANT], false],
            'microsoft personal account' => [SsoProviderType::MICROSOFT, true, ['email' => 'a@outlook.com', 'xms_edov' => true, 'tid' => SsoIdentityClaimsMapper::MICROSOFT_CONSUMER_TENANT_ID], false],
            'oidc trusted by the operator' => [SsoProviderType::OIDC, true, ['email' => 'a@acme.com', 'email_verified' => 'true'], true],
            'oidc not trusted by the operator' => [SsoProviderType::OIDC, false, ['email' => 'a@acme.com', 'email_verified' => true], false],
            'oidc unverified' => [SsoProviderType::OIDC, true, ['email' => 'a@acme.com'], false],
            'no email' => [SsoProviderType::GOOGLE, true, ['email_verified' => true], false],
        ];
    }

    public function test_microsoft_identities_use_the_object_id_and_tenant_issuer(): void
    {
        $identity = (new SsoIdentityClaimsMapper)->map($this->provider(SsoProviderType::MICROSOFT), [
            'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'sub' => 'pairwise-subject',
            'oid' => 'object-id',
            'tid' => self::TENANT,
            'preferred_username' => 'someone@acme.com',
        ]);

        $this->assertSame('object-id', $identity->subject);
        $this->assertSame('https://login.microsoftonline.com/'.self::TENANT.'/v2.0', $identity->issuer);
        $this->assertNull($identity->email);
    }

    public function test_microsoft_identities_without_an_object_id_are_rejected(): void
    {
        $this->expectException(SsoException::class);

        (new SsoIdentityClaimsMapper)->map($this->provider(SsoProviderType::MICROSOFT), [
            'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'sub' => 'pairwise-subject',
        ]);
    }

    public function test_google_issuers_are_normalised(): void
    {
        $identity = (new SsoIdentityClaimsMapper)->map($this->provider(SsoProviderType::GOOGLE), [
            'iss' => 'accounts.google.com',
            'sub' => '1234',
        ]);

        $this->assertSame('https://accounts.google.com', $identity->issuer);
    }

    public function test_names_fall_back_to_the_full_name_and_are_trimmed(): void
    {
        $identity = (new SsoIdentityClaimsMapper)->map($this->provider(SsoProviderType::OIDC), [
            'sub' => 'subject',
            'name' => '  Grace   Brewster Hopper ',
            'email' => ' Grace@Example.com ',
        ]);

        $this->assertSame('Grace', $identity->firstName);
        $this->assertSame('Brewster Hopper', $identity->lastName);
        $this->assertSame('grace@example.com', $identity->email);
    }

    public function test_connection_identities_carry_the_connection_and_only_provider_verified_emails_are_trusted(): void
    {
        $okta = $this->connection(SsoProviderType::OIDC);

        $verified = (new SsoIdentityClaimsMapper)->map($okta, ['sub' => 's-1', 'email' => 'ada@acme.test', 'email_verified' => true, 'groups' => ['Admins', '', 42, 'Staff']]);
        $this->assertTrue($verified->emailTrusted);
        $this->assertSame(7, $verified->connectionId);
        $this->assertSame(['Admins', 'Staff'], $verified->groups);

        $this->assertFalse((new SsoIdentityClaimsMapper)->map($okta, ['sub' => 's-1', 'email' => 'ada@acme.test'])->emailTrusted);

        $entra = $this->connection(SsoProviderType::MICROSOFT);
        $this->assertFalse((new SsoIdentityClaimsMapper)->map($entra, ['iss' => 'https://login.microsoftonline.com/t/v2.0', 'oid' => 'o-1', 'email' => 'ada@acme.test', 'email_verified' => true])->emailTrusted);
        $this->assertTrue((new SsoIdentityClaimsMapper)->map($entra, ['iss' => 'https://login.microsoftonline.com/t/v2.0', 'oid' => 'o-1', 'email' => 'ada@acme.test', 'xms_edov' => true])->emailTrusted);
    }

    private function connection(SsoProviderType $type): SsoProviderConfig
    {
        return new SsoProviderConfig(
            key: 'connection-7',
            type: $type,
            name: 'Okta',
            issuer: 'https://acme.okta.test',
            clientId: 'client',
            clientSecret: 'secret',
            logoUrl: null,
            trustEmail: false,
            connectionId: 7,
            accountId: 3,
        );
    }

    private function provider(SsoProviderType $type, bool $trustEmail = true): SsoProviderConfig
    {
        return new SsoProviderConfig(
            key: strtolower($type->value),
            type: $type,
            name: 'Test',
            issuer: 'https://idp.example.test',
            clientId: 'client',
            clientSecret: 'secret',
            logoUrl: null,
            trustEmail: $trustEmail,
        );
    }
}
