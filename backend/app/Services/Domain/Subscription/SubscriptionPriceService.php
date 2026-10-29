<?php

namespace HiEvents\Services\Domain\Subscription;

use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Services\Domain\Subscription\DTO\SubscriptionPriceDTO;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;

class SubscriptionPriceService
{
    private const CACHE_KEY = 'account_subscription_price_catalog';

    private const CACHE_TTL_SECONDS = 3600;

    private const EMPTY_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly Repository $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return SubscriptionPriceDTO[]
     */
    public function getPrices(?string $preferredCurrency): array
    {
        $catalog = $this->catalog();
        $currency = $this->currencyFor($catalog, strtoupper((string) $preferredCurrency));

        return collect($catalog)
            ->filter(fn (array $entry) => isset($entry['amounts'][$currency]))
            ->map(fn (array $entry) => new SubscriptionPriceDTO(
                interval: SubscriptionBillingInterval::from($entry['interval']),
                priceId: $entry['price_id'],
                unitAmount: $entry['amounts'][$currency],
                currency: $currency,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{interval: string, price_id: string, default_currency: string, amounts: array<string, int>}>  $catalog
     */
    private function currencyFor(array $catalog, string $preferredCurrency): string
    {
        $offeredEverywhere = $catalog !== [] && collect($catalog)
            ->every(fn (array $entry) => isset($entry['amounts'][$preferredCurrency]));

        if ($offeredEverywhere) {
            return $preferredCurrency;
        }

        return collect($catalog)->first()['default_currency'] ?? $preferredCurrency;
    }

    /**
     * @return array<string, array{interval: string, price_id: string, default_currency: string, amounts: array<string, int>}>
     */
    private function catalog(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $catalog = $this->fetchCatalog();
        } catch (ApiErrorException|StripeClientConfigurationException $exception) {
            $this->logger->warning('Failed to fetch subscription prices from Stripe', [
                'error' => $exception->getMessage(),
            ]);
            $catalog = [];
        }

        $this->cache->put(
            self::CACHE_KEY,
            $catalog,
            $catalog === [] ? self::EMPTY_CACHE_TTL_SECONDS : self::CACHE_TTL_SECONDS,
        );

        return $catalog;
    }

    /**
     * @return array<string, array{interval: string, price_id: string, default_currency: string, amounts: array<string, int>}>
     *
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    private function fetchCatalog(): array
    {
        $intervalsByLookupKey = collect(SubscriptionBillingInterval::cases())
            ->mapWithKeys(fn (SubscriptionBillingInterval $interval) => [$interval->priceLookupKey() => $interval]);

        $prices = $this->stripeClientFactory->createForBilling()->prices->all([
            'lookup_keys' => $intervalsByLookupKey->keys()->all(),
            'active' => true,
            'expand' => ['data.currency_options'],
        ])->data;

        $catalog = [];
        foreach ($intervalsByLookupKey as $lookupKey => $interval) {
            $price = collect($prices)->first(fn (Price $price) => $price->lookup_key === $lookupKey && $price->unit_amount !== null);
            if ($price === null) {
                continue;
            }

            $catalog[$interval->value] = [
                'interval' => $interval->value,
                'price_id' => $price->id,
                'default_currency' => strtoupper($price->currency),
                'amounts' => $this->amountsByCurrency($price),
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, int>
     */
    private function amountsByCurrency(Price $price): array
    {
        $amounts = [strtoupper($price->currency) => $price->unit_amount];

        foreach ($price->currency_options?->toArray() ?? [] as $currency => $option) {
            if (isset($option['unit_amount'])) {
                $amounts[strtoupper($currency)] = $option['unit_amount'];
            }
        }

        return $amounts;
    }
}
