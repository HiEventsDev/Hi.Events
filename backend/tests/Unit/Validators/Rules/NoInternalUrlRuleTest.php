<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\Validators\Rules\NoInternalUrlRule;
use Tests\TestCase;

class NoInternalUrlRuleTest extends TestCase
{
    private NoInternalUrlRule $rule;
    private array $failedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NoInternalUrlRule();
        $this->failedMessages = [];
    }

    private function validate(string $url): bool
    {
        $this->failedMessages = [];
        $failed = false;

        $this->rule->validate('url', $url, function ($message) use (&$failed) {
            $failed = true;
            $this->failedMessages[] = $message;
        });

        return !$failed;
    }

    public function testAcceptsValidExternalUrls(): void
    {
        $this->assertTrue($this->validate('https://example.com/webhook'));
        $this->assertTrue($this->validate('https://api.stripe.com/v1/webhooks'));
        $this->assertTrue($this->validate('https://hooks.slack.com/services/123'));
        $this->assertTrue($this->validate('http://webhook.site/abc123'));
    }

    public function testRejectsLocalhostUrls(): void
    {
        $this->assertFalse($this->validate('http://localhost/admin'));
        $this->assertFalse($this->validate('http://localhost:8080/api'));
        $this->assertFalse($this->validate('https://localhost/webhook'));
    }

    public function testRejectsLoopbackIpUrls(): void
    {
        $this->assertFalse($this->validate('http://127.0.0.1/admin'));
        $this->assertFalse($this->validate('http://127.0.0.1:3000/api'));
        $this->assertFalse($this->validate('https://127.0.0.1/webhook'));
    }

    public function testRejectsCloudMetadataUrls(): void
    {
        $this->assertFalse($this->validate('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($this->validate('http://169.254.169.254/latest/meta-data/iam/security-credentials/'));
        $this->assertFalse($this->validate('http://metadata.google.internal/computeMetadata/v1/'));
    }

    public function testRejectsPrivateIpAddresses(): void
    {
        $this->assertFalse($this->validate('http://10.0.0.1/internal'));
        $this->assertFalse($this->validate('http://10.255.255.255/api'));
        $this->assertFalse($this->validate('http://172.16.0.1/webhook'));
        $this->assertFalse($this->validate('http://172.31.255.255/api'));
        $this->assertFalse($this->validate('http://192.168.0.1/admin'));
        $this->assertFalse($this->validate('http://192.168.255.255/api'));
    }

    public function testRejectsZeroIpAddress(): void
    {
        $this->assertFalse($this->validate('http://0.0.0.0/'));
        $this->assertFalse($this->validate('http://0.0.0.0:8080/webhook'));
    }

    public function testRejectsLinkLocalAddresses(): void
    {
        $this->assertFalse($this->validate('http://169.254.0.1/'));
        $this->assertFalse($this->validate('http://169.254.255.254/'));
    }

    public function testRejectsInvalidUrls(): void
    {
        $this->assertFalse($this->validate('not-a-url'));
        $this->assertFalse($this->validate(''));
    }

    public function testRejectsIpv6Localhost(): void
    {
        $this->assertFalse($this->validate('http://[::1]/webhook'));
    }

    public function testRejectsNonHttpSchemes(): void
    {
        $this->assertFalse($this->validate('file:///etc/passwd'));
        $this->assertFalse($this->validate('gopher://localhost/'));
        $this->assertFalse($this->validate('ftp://example.com/'));
        $this->assertFalse($this->validate('dict://localhost/'));
    }

    public function testRejectsLocalhostTld(): void
    {
        $this->assertFalse($this->validate('http://app.localhost/webhook'));
        $this->assertFalse($this->validate('https://api.localhost/'));
        $this->assertFalse($this->validate('http://anything.localhost/'));
    }

    public function testAcceptsHttpAndHttpsSchemes(): void
    {
        $this->assertTrue($this->validate('http://example.com/webhook'));
        $this->assertTrue($this->validate('https://example.com/webhook'));
    }
}
