<?php

namespace Tests\Unit\Services\Domain\Account;

use HiEvents\DomainObjects\Enums\AttributionSourceType;
use HiEvents\Services\Domain\Account\AttributionSourceClassifier;
use Illuminate\Config\Repository;
use Tests\TestCase;

class AttributionSourceClassifierTest extends TestCase
{
    private AttributionSourceClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new AttributionSourceClassifier(new Repository([
            'app' => [
                'url' => 'https://api.hi.events',
                'frontend_url' => 'https://app.hi.events',
            ],
        ]));
    }

    public function test_gclid_is_paid(): void
    {
        $this->assertSame(AttributionSourceType::PAID, $this->classify(gclid: 'abc'));
    }

    public function test_google_ads_privacy_click_ids_are_paid(): void
    {
        $this->assertSame(AttributionSourceType::PAID, $this->classify(utmRaw: ['gbraid' => 'x']));
        $this->assertSame(AttributionSourceType::PAID, $this->classify(utmRaw: ['wbraid' => 'x']));
    }

    public function test_paid_medium_is_paid_regardless_of_case(): void
    {
        $this->assertSame(AttributionSourceType::PAID, $this->classify(utmMedium: ' CPC '));
        $this->assertSame(AttributionSourceType::PAID, $this->classify(utmMedium: 'paid_social'));
    }

    public function test_fbclid_alone_is_referral(): void
    {
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(fbclid: 'abc'));
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(fbclid: 'abc', referrerUrl: 'https://hi.events/'));
    }

    public function test_fbclid_with_paid_medium_is_paid(): void
    {
        $this->assertSame(AttributionSourceType::PAID, $this->classify(utmMedium: 'paid_social', fbclid: 'abc'));
    }

    public function test_marketing_site_referrer_is_organic(): void
    {
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(
            utmMedium: 'website',
            referrerUrl: 'https://hi.events/pricing',
        ));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(
            referrerUrl: 'https://www.hi.events/',
        ));
    }

    public function test_app_referrer_is_organic(): void
    {
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(
            referrerUrl: 'https://app.hi.events/auth/login',
        ));
    }

    public function test_search_engine_referrer_is_organic(): void
    {
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: 'https://www.google.com/'));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: 'https://www.google.co.uk/search?q=x'));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: 'https://duckduckgo.com/'));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: 'https://search.brave.com/'));
    }

    public function test_external_referrer_is_referral(): void
    {
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(
            referrerUrl: 'https://github.com/HiEventsDev/hi.events',
        ));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: 'https://www.google.com.au/'));
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(referrerUrl: 'https://notgoogle.example/'));
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(referrerUrl: 'https://google.evil.com/'));
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(referrerUrl: 'https://mail.google.anything.io/'));
    }

    public function test_lookalike_domain_is_referral(): void
    {
        $this->assertSame(AttributionSourceType::REFERRAL, $this->classify(
            referrerUrl: 'https://nothi.events/',
        ));
    }

    public function test_has_paid_click_id_covers_gclid_and_raw_click_ids(): void
    {
        $this->assertTrue($this->classifier->hasPaidClickId('abc', null));
        $this->assertTrue($this->classifier->hasPaidClickId(null, ['wbraid' => 'x']));
        $this->assertFalse($this->classifier->hasPaidClickId(null, ['fbclid' => 'x', 'ref' => 'hero-cta']));
        $this->assertFalse($this->classifier->hasPaidClickId(null, null));
    }

    public function test_no_referrer_is_organic(): void
    {
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(utmMedium: 'website'));
        $this->assertSame(AttributionSourceType::ORGANIC, $this->classify(referrerUrl: '  '));
    }

    private function classify(
        ?string $utmMedium = null,
        ?string $referrerUrl = null,
        ?string $gclid = null,
        ?string $fbclid = null,
        ?array $utmRaw = null,
    ): AttributionSourceType {
        return $this->classifier->classify(
            utmMedium: $utmMedium,
            referrerUrl: $referrerUrl,
            gclid: $gclid,
            fbclid: $fbclid,
            utmRaw: $utmRaw,
        );
    }
}
