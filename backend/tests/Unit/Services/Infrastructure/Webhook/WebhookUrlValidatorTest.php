<?php

namespace Tests\Unit\Services\Infrastructure\Webhook;

use HiEvents\Exceptions\UnsafeWebhookUrlException;
use HiEvents\Services\Infrastructure\Webhook\DTO\WebhookTargetDTO;
use HiEvents\Services\Infrastructure\Webhook\WebhookUrlValidator;
use Tests\TestCase;

class WebhookUrlValidatorTest extends TestCase
{
    private WebhookUrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.allowed_internal_webhook_hosts' => '']);

        $this->validator = new WebhookUrlValidator;
    }

    /**
     * @dataProvider blockedUrlProvider
     */
    public function test_it_rejects_urls_resolving_to_internal_addresses(string $url): void
    {
        $this->expectException(UnsafeWebhookUrlException::class);

        $this->validator->validate($url);
    }

    public static function blockedUrlProvider(): array
    {
        return [
            'localhost' => ['http://localhost/hook'],
            'loopback ipv4' => ['http://127.0.0.1/hook'],
            'loopback ipv6' => ['http://[::1]/hook'],
            'unspecified' => ['http://0.0.0.0/hook'],
            'private class a' => ['http://10.0.0.1/hook'],
            'private class b' => ['http://172.16.0.1/hook'],
            'private class c' => ['http://192.168.1.1/hook'],
            'link local' => ['http://169.254.1.1/hook'],
            'aws metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'gcp metadata' => ['http://metadata.google.internal/hook'],
            'localhost tld' => ['http://service.localhost/hook'],
            'internal tld' => ['http://api.internal/hook'],
            'ipv4 mapped ipv6' => ['http://[::ffff:127.0.0.1]/hook'],
            'ipv4 mapped private' => ['http://[::ffff:10.0.0.1]/hook'],
            'six to four loopback' => ['http://[2002:7f00:1::]/hook'],
            'six to four private' => ['http://[2002:a00:1::]/hook'],
            'nat64 loopback' => ['http://[64:ff9b::7f00:1]/hook'],
            'teredo loopback' => ['http://[2001:0:0:0:0:0:80ff:fffe]/hook'],
            'ipv4 compatible' => ['http://[::7f00:1]/hook'],
            'unique local ipv6' => ['http://[fc00::1]/hook'],
            'link local ipv6' => ['http://[fe80::1]/hook'],
            'ftp scheme' => ['ftp://example.com/hook'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://example.com/hook'],
        ];
    }

    /**
     * @dataProvider allowedUrlProvider
     */
    public function test_it_allows_public_addresses(string $url, string $expectedHost): void
    {
        $target = $this->validator->validate($url);

        $this->assertSame($expectedHost, $target->host);
    }

    public static function allowedUrlProvider(): array
    {
        return [
            'public ipv4' => ['https://8.8.8.8/hook', '8.8.8.8'],
            'public ipv6' => ['https://[2606:4700:4700::1111]/hook', '2606:4700:4700::1111'],
            'six to four public' => ['https://[2002:808:808::]/hook', '2002:808:808::'],
            'nat64 public' => ['https://[64:ff9b::808:808]/hook', '64:ff9b::808:808'],
        ];
    }

    public function test_it_defaults_the_port_by_scheme(): void
    {
        $this->assertSame(443, $this->validator->validate('https://8.8.8.8/hook')->port);
        $this->assertSame(80, $this->validator->validate('http://8.8.8.8/hook')->port);
        $this->assertSame(8443, $this->validator->validate('https://8.8.8.8:8443/hook')->port);
    }

    public function test_it_pins_the_resolved_address_for_curl(): void
    {
        $target = $this->validator->validate('https://8.8.8.8/hook');

        $this->assertSame(['8.8.8.8:443:8.8.8.8'], $target->toCurlResolveEntries());
    }

    public function test_it_brackets_ipv6_addresses_in_curl_resolve_entries(): void
    {
        $target = $this->validator->validate('https://[2606:4700:4700::1111]/hook');

        $this->assertSame(
            ['2606:4700:4700::1111:443:[2606:4700:4700::1111]'],
            $target->toCurlResolveEntries(),
        );
    }

    public function test_it_pins_every_resolved_address_in_a_single_entry(): void
    {
        $target = new WebhookTargetDTO(
            host: 'hooks.example.com',
            port: 443,
            ipAddresses: ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
        );

        $this->assertSame(
            ['hooks.example.com:443:93.184.216.34,[2606:2800:220:1:248:1893:25c8:1946]'],
            $target->toCurlResolveEntries(),
        );
    }

    public function test_it_allows_explicitly_whitelisted_internal_hosts(): void
    {
        config(['app.allowed_internal_webhook_hosts' => '10.0.0.5']);

        $target = $this->validator->validate('http://10.0.0.5/hook');

        $this->assertSame('10.0.0.5', $target->host);
    }
}
