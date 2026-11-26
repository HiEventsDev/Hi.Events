<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Services\Infrastructure\Url\PublicApiUrl;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignUnsubscribeTokenServiceTest extends TestCase
{
    private CampaignUnsubscribeTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = $this->tokensSignedWith('first-secret');
    }

    public function test_a_token_resolves_back_to_its_recipient(): void
    {
        $this->assertSame(42, $this->tokens->recipientIdFrom($this->tokens->tokenFor(42)));
        $this->assertSame($this->tokens->tokenFor(42), $this->tokens->tokenFor(42));
        $this->assertNotSame($this->tokens->tokenFor(42), $this->tokens->tokenFor(43));
    }

    public function test_the_token_does_not_reveal_the_app_key(): void
    {
        $this->assertStringNotContainsString('first-secret', $this->tokens->tokenFor(42));
    }

    public static function forgedTokens(): array
    {
        return [
            'empty' => [''],
            'id only' => ['42'],
            'id with an empty signature' => ['42.'],
            'signature only' => ['.abcdef'],
            'non numeric id' => ['abc.abcdef'],
            'negative id' => ['-42.abcdef'],
            'id with trailing text' => ['42abc.abcdef'],
            'wrong signature' => ['42.'.'0000000000000000000000000000000000000000'],
        ];
    }

    #[DataProvider('forgedTokens')]
    public function test_a_forged_token_resolves_to_nobody(string $token): void
    {
        $this->assertNull($this->tokens->recipientIdFrom($token));
    }

    public function test_a_signature_cannot_be_reused_for_another_recipient(): void
    {
        $signature = explode('.', $this->tokens->tokenFor(42), 2)[1];

        $this->assertNull($this->tokens->recipientIdFrom('43.'.$signature));
        $this->assertNull($this->tokens->recipientIdFrom('42.'.substr($signature, 0, -1)));
        $this->assertNull($this->tokens->recipientIdFrom('42.'.$signature.'0'));
        $this->assertNull($this->tokens->recipientIdFrom('042.'.$signature.'x'));
    }

    public function test_a_token_signed_with_another_app_key_is_rejected(): void
    {
        $other = $this->tokensSignedWith('second-secret');

        $this->assertNull($this->tokens->recipientIdFrom($other->tokenFor(42)));
    }

    public function test_a_short_token_resolves_back_to_its_recipient(): void
    {
        $this->assertSame(42, $this->tokens->recipientIdFrom($this->tokens->shortTokenFor(42)));
        $this->assertNotSame($this->tokens->shortTokenFor(42), $this->tokens->shortTokenFor(43));
        $this->assertLessThan(strlen($this->tokens->tokenFor(42)), strlen($this->tokens->shortTokenFor(42)));
    }

    public function test_a_short_signature_cannot_be_reused_for_another_recipient(): void
    {
        $signature = explode('.', $this->tokens->shortTokenFor(42), 2)[1];

        $this->assertNull($this->tokens->recipientIdFrom('43.'.$signature));
        $this->assertNull($this->tokens->recipientIdFrom('42.'.substr($signature, 0, -1)));
        $this->assertNull($this->tokens->recipientIdFrom('42.'.str_repeat('0', strlen($signature))));
    }

    public function test_the_long_and_short_signatures_cannot_stand_in_for_each_other(): void
    {
        $long = explode('.', $this->tokens->tokenFor(42), 2)[1];
        $short = explode('.', $this->tokens->shortTokenFor(42), 2)[1];

        $this->assertNull($this->tokens->recipientIdFrom('42.'.substr($long, 0, strlen($short))));
        $this->assertNull($this->tokens->recipientIdFrom('42.'.str_pad($short, strlen($long), '0')));
    }

    public function test_a_short_token_signed_with_another_app_key_is_rejected(): void
    {
        $other = $this->tokensSignedWith('second-secret');

        $this->assertNull($this->tokens->recipientIdFrom($other->shortTokenFor(42)));
    }

    public function test_the_sample_link_shown_in_previews_and_test_texts_resolves_to_nobody(): void
    {
        $sampleToken = basename((string) parse_url($this->tokens->sampleSmsUrl(), PHP_URL_PATH));

        $this->assertNull($this->tokens->recipientIdFrom($sampleToken));
        $this->assertSame(strlen($this->tokens->smsUrl(1234567)), strlen($this->tokens->sampleSmsUrl()));
    }

    public function test_the_urls_carry_the_token(): void
    {
        $this->assertStringEndsWith('/audience/unsubscribe/'.$this->tokens->tokenFor(42), $this->tokens->pageUrl(42));
        $this->assertStringEndsWith('/u/'.$this->tokens->shortTokenFor(42), $this->tokens->smsUrl(42));
    }

    private function tokensSignedWith(string $key): CampaignUnsubscribeTokenService
    {
        $config = new Repository(['app' => ['key' => $key]]);

        return new CampaignUnsubscribeTokenService($config, new PublicApiUrl($config));
    }
}
