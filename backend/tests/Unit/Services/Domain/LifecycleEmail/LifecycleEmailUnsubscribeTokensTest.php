<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\LifecycleEmail;

use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailUnsubscribeTokens;
use HiEvents\Services\Infrastructure\Url\PublicApiUrl;
use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LifecycleEmailUnsubscribeTokensTest extends TestCase
{
    private LifecycleEmailUnsubscribeTokens $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = $this->tokensFor(['key' => 'test-key']);
    }

    public function test_a_token_resolves_back_to_its_user(): void
    {
        $this->assertSame(42, $this->tokens->userIdFrom($this->tokens->tokenFor(42)));
    }

    public function test_a_token_cannot_be_moved_to_another_user(): void
    {
        [, $signature] = explode('.', $this->tokens->tokenFor(42));

        $this->assertNull($this->tokens->userIdFrom('43.'.$signature));
    }

    public function test_malformed_tokens_are_rejected(): void
    {
        foreach (['', '42', '42.', 'abc.def', '42.'.str_repeat('z', 64), '-1.'.str_repeat('a', 64)] as $token) {
            $this->assertNull($this->tokens->userIdFrom($token), $token);
        }
    }

    public function test_tokens_signed_with_another_key_are_rejected(): void
    {
        $other = $this->tokensFor(['key' => 'other-key']);

        $this->assertNull($this->tokens->userIdFrom($other->tokenFor(42)));
    }

    public static function installs(): array
    {
        return [
            'api behind the site under /api, APP_URL never set' => ['http://localhost:5173', 'https://localhost:8443', 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'api behind the site under /api, APP_URL set to the site address' => ['https://tickets.example.com', 'https://tickets.example.com', 'https://tickets.example.com', 'https://tickets.example.com/api'],
            'api on its own domain' => ['https://api.example.com', 'https://api.example.com', 'https://tickets.example.com', 'https://api.example.com'],
        ];
    }

    #[DataProvider('installs')]
    public function test_the_one_click_unsubscribe_address_is_one_a_mail_provider_can_reach(string $url, string $apiUrl, string $frontendUrl, string $expectedBase): void
    {
        $tokens = $this->tokensFor(['key' => 'test-key', 'url' => $url, 'api_url' => $apiUrl, 'frontend_url' => $frontendUrl]);

        $this->assertSame(
            $expectedBase.'/public/lifecycle-emails/unsubscribe/'.$tokens->tokenFor(42),
            $tokens->oneClickUrlFor(42),
        );
    }

    private function tokensFor(array $appConfig): LifecycleEmailUnsubscribeTokens
    {
        $config = new ConfigRepository(['app' => $appConfig]);

        return new LifecycleEmailUnsubscribeTokens($config, new PublicApiUrl($config));
    }
}
