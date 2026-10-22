<?php

namespace Tests\Unit\Services\Domain\Subscription;

use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;
use HiEvents\Services\Domain\Subscription\SubscriptionPriceService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Stripe\Collection;
use Stripe\Exception\ApiConnectionException;
use Stripe\Price;
use Stripe\Service\PriceService;
use Stripe\StripeClient;
use Tests\TestCase;

class SubscriptionPriceServiceTest extends TestCase
{
    private PriceService|MockInterface $stripePrices;

    private SubscriptionPriceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripePrices = Mockery::mock(PriceService::class);

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('prices')->andReturn($this->stripePrices);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);

        $this->service = new SubscriptionPriceService($stripeClientFactory, new Repository(new ArrayStore), new NullLogger);
    }

    public function test_prices_are_shown_in_the_accounts_currency_when_stripe_offers_it(): void
    {
        $this->givenCatalog();

        $this->assertSame(
            [[SubscriptionBillingInterval::MONTH, 3399, 'USD'], [SubscriptionBillingInterval::YEAR, 33599, 'USD']],
            $this->summarise($this->service->getPrices('usd')),
        );
    }

    public function test_prices_fall_back_to_the_default_currency(): void
    {
        $this->givenCatalog();

        $this->assertSame(
            [[SubscriptionBillingInterval::MONTH, 2999, 'EUR'], [SubscriptionBillingInterval::YEAR, 29999, 'EUR']],
            $this->summarise($this->service->getPrices('CAD')),
        );
    }

    public function test_a_currency_only_some_prices_offer_falls_back_to_the_default(): void
    {
        $this->stripePrices->shouldReceive('all')->andReturn($this->prices([
            ['hi_events_pro_monthly', 2999, ['gbp' => 2499]],
            ['hi_events_pro_yearly', 29999, []],
        ]));

        $this->assertSame('EUR', $this->service->getPrices('GBP')[0]->currency);
    }

    public function test_prices_carry_the_stripe_price_ids(): void
    {
        $this->givenCatalog();

        $this->assertSame(
            ['price_hi_events_pro_monthly', 'price_hi_events_pro_yearly'],
            array_map(fn ($price) => $price->priceId, $this->service->getPrices('GBP')),
        );
    }

    public function test_prices_with_mismatched_default_currencies_skip_the_one_that_cannot_be_shown(): void
    {
        $this->stripePrices->shouldReceive('all')->andReturn(Collection::constructFrom([
            'object' => 'list',
            'data' => [
                Price::constructFrom(['id' => 'price_m', 'lookup_key' => 'hi_events_pro_monthly', 'unit_amount' => 2999, 'currency' => 'eur']),
                Price::constructFrom(['id' => 'price_y', 'lookup_key' => 'hi_events_pro_yearly', 'unit_amount' => 33599, 'currency' => 'usd']),
            ],
        ]));

        $this->assertSame(
            [[SubscriptionBillingInterval::MONTH, 2999, 'EUR']],
            $this->summarise($this->service->getPrices('GBP')),
        );
    }

    public function test_the_catalog_is_cached_between_calls(): void
    {
        $this->givenCatalog();

        $this->service->getPrices('EUR');
        $this->service->getPrices('USD');

        $this->stripePrices->shouldHaveReceived('all')->once();
    }

    public function test_returns_no_prices_when_stripe_is_unavailable_without_retrying_on_every_request(): void
    {
        $this->stripePrices->shouldReceive('all')
            ->once()
            ->andThrow(new ApiConnectionException('Stripe is unreachable'));

        $this->assertSame([], $this->service->getPrices('EUR'));
        $this->assertSame([], $this->service->getPrices('EUR'));
    }

    private function givenCatalog(): void
    {
        $this->stripePrices->shouldReceive('all')
            ->with([
                'lookup_keys' => ['hi_events_pro_monthly', 'hi_events_pro_yearly'],
                'active' => true,
                'expand' => ['data.currency_options'],
            ])
            ->andReturn($this->prices([
                ['hi_events_pro_yearly', 29999, ['usd' => 33599, 'gbp' => 25099]],
                ['hi_events_pro_monthly', 2999, ['usd' => 3399, 'gbp' => 2499]],
            ]));
    }

    private function prices(array $prices): Collection
    {
        return Collection::constructFrom([
            'object' => 'list',
            'data' => array_map(fn (array $price) => Price::constructFrom([
                'id' => 'price_'.$price[0],
                'lookup_key' => $price[0],
                'unit_amount' => $price[1],
                'currency' => 'eur',
                'currency_options' => array_map(fn (int $amount) => ['unit_amount' => $amount], $price[2])
                    + ['eur' => ['unit_amount' => $price[1]]],
            ]), $prices),
        ]);
    }

    private function summarise(array $prices): array
    {
        return array_map(fn ($price) => [$price->interval, $price->unitAmount, $price->currency], $prices);
    }
}
