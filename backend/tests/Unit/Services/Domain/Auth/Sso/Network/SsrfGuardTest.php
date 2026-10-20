<?php

namespace Tests\Unit\Services\Domain\Auth\Sso\Network;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\Network\HostResolver;
use HiEvents\Services\Domain\Auth\Sso\Network\SsrfGuard;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SsrfGuardTest extends TestCase
{
    public static function blockedAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'cloud metadata' => ['169.254.169.254'],
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12' => ['172.20.0.1'],
            'private 192.168/16' => ['192.168.1.1'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'unspecified' => ['0.0.0.0'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unique local' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv4-mapped IPv6' => ['::ffff:127.0.0.1'],
            'NAT64 prefix' => ['64:ff9b::a00:1'],
            '6to4' => ['2002:a00:1::1'],
        ];
    }

    #[DataProvider('blockedAddresses')]
    public function test_non_public_addresses_are_refused(string $address): void
    {
        $guard = $this->guardResolvingTo([$address]);

        $this->assertFalse($guard->isPublicAddress($address));

        try {
            $guard->pin('https://idp.example.test/.well-known/openid-configuration');
            $this->fail('Expected the address to be refused');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::ISSUER_NOT_ALLOWED, $exception->errorCode);
        }
    }

    public function test_a_public_address_is_pinned_for_curl(): void
    {
        $target = $this->guardResolvingTo(['93.184.216.34'])->pin('https://idp.example.test:8443/path');

        $this->assertSame('idp.example.test:8443:93.184.216.34', $target->curlResolveEntry());
    }

    public function test_an_ipv6_address_is_bracketed_when_pinned(): void
    {
        $target = $this->guardResolvingTo(['2606:2800:220:1:248:1893:25c8:1946'])->pin('https://idp.example.test');

        $this->assertSame('idp.example.test:443:[2606:2800:220:1:248:1893:25c8:1946]', $target->curlResolveEntry());
    }

    public function test_a_host_with_any_private_address_is_refused(): void
    {
        $this->expectException(SsoException::class);

        $this->guardResolvingTo(['93.184.216.34', '10.0.0.1'])->pin('https://idp.example.test');
    }

    public function test_plain_http_and_credentials_in_the_url_are_refused(): void
    {
        foreach (['http://idp.example.test', 'https://user:pass@idp.example.test', 'ftp://idp.example.test'] as $url) {
            try {
                $this->guardResolvingTo(['93.184.216.34'])->pin($url);
                $this->fail("Expected $url to be refused");
            } catch (SsoException $exception) {
                $this->assertSame(SsoErrorCode::ISSUER_NOT_ALLOWED, $exception->errorCode);
            }
        }
    }

    public function test_an_unresolvable_host_is_reported_as_unavailable(): void
    {
        try {
            $this->guardResolvingTo([])->pin('https://missing.example.test');
            $this->fail('Expected the host to be unavailable');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::PROVIDER_UNAVAILABLE, $exception->errorCode);
        }
    }

    private function guardResolvingTo(array $addresses): SsrfGuard
    {
        $resolver = Mockery::mock(HostResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($addresses);

        return new SsrfGuard($resolver);
    }
}
