<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsProviderInterface;
use HiEvents\Services\Infrastructure\Url\PublicApiUrl;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicApiUrlTest extends TestCase
{
    private const APP_URL_FALLBACK = 'http://localhost:5173';

    private const API_URL_FALLBACK = 'https://localhost:8443';

    public static function installs(): array
    {
        return [
            'all-in-one image, APP_URL never set' => [null, 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'APP_URL set to the site address behind the bundled proxy' => ['http://localhost:8123', 'http://localhost:8123', 'http://localhost:8123/api'],
            'APP_URL set to the site address with a trailing slash and another scheme' => ['http://Tickets.example.com/', 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'APP_URL forwarded to the container empty' => ['', 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'APP_URL set to the site address with the default port spelled out' => ['https://tickets.example.com:443', 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'APP_URL set to the site address while the site spells out the default port' => ['http://tickets.example.com', 'http://tickets.example.com:80/', 'http://tickets.example.com:80/api'],
            'docker development, APP_URL already is the api address' => ['https://localhost:8443/api', 'https://localhost:8443', 'https://localhost:8443/api'],
            'api on its own domain' => ['https://api.example.com', 'https://tickets.example.com', 'https://api.example.com'],
            'api on its own port' => ['http://localhost:8000', 'http://localhost:5678', 'http://localhost:8000'],
            'api under its own path on another host' => ['https://example.com/backend/', 'https://tickets.example.com/', 'https://example.com/backend'],
        ];
    }

    #[DataProvider('installs')]
    public function test_addresses_outside_services_call_are_built_on_the_address_the_api_is_really_served_from(?string $appUrl, string $frontendUrl, string $expectedBase): void
    {
        $config = $this->configFor($appUrl, $frontendUrl);
        $tokens = new CampaignUnsubscribeTokenService($config, new PublicApiUrl($config));

        $this->assertSame($expectedBase.'/public/crm/unsubscribe/'.$tokens->tokenFor(42), $tokens->oneClickUrl(42));
    }

    #[DataProvider('installs')]
    public function test_twilio_is_given_webhook_addresses_it_can_reach(?string $appUrl, string $frontendUrl, string $expectedBase): void
    {
        $connections = new SmsConnectionService(
            Mockery::mock(CrmSmsConnectionRepositoryInterface::class),
            Mockery::mock(SmsProviderInterface::class),
            Mockery::mock(DatabaseManager::class),
            $this->configFor($appUrl, $frontendUrl),
        );

        $this->assertSame($expectedBase.'/public/webhooks/sms/twilio/abc123/inbound', $connections->inboundUrl('abc123'));
        $this->assertSame($expectedBase.'/public/webhooks/sms/twilio/abc123/status', $connections->statusUrl('abc123'));
    }

    private function configFor(?string $appUrl, string $frontendUrl): Repository
    {
        return new Repository(['app' => [
            'key' => 'secret',
            'url' => $appUrl ?? self::APP_URL_FALLBACK,
            'api_url' => $appUrl ?? self::API_URL_FALLBACK,
            'frontend_url' => $frontendUrl,
        ]]);
    }
}
