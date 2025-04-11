<?php

namespace HiEvents\Services\Infrastructure\CurrencyConversion;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\Services\Infrastructure\CurrencyConversion\Exception\CurrencyConversionErrorException;
use HiEvents\Values\MoneyValue;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class OpenExchangeRatesCurrencyConversionClient implements CurrencyConversionClientInterface
{
    private string $apiKey;

    private const CACHE_TTL = 43200; // 12 hours in seconds
    private const API_URL = 'https://openexchangerates.org/api/latest.json';

    public function __construct(
        string                           $apiKey,
        private readonly CacheInterface  $cache,
        private readonly LoggerInterface $logger,
    )
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param Currency $fromCurrency
     * @param Currency $toCurrency
     * @param float $amount
     * @return MoneyValue
     * @throws CurrencyConversionErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     */
    public function convert(Currency $fromCurrency, Currency $toCurrency, float $amount): MoneyValue
    {
        if ($fromCurrency->getCurrencyCode() === $toCurrency->getCurrencyCode()) {
            return MoneyValue::fromFloat($amount, $toCurrency->getCurrencyCode());
        }

        $rates = $this->getRates();

        $fromCurrencyCode = $fromCurrency->getCurrencyCode();
        $toCurrencyCode = $toCurrency->getCurrencyCode();

        if (!isset($rates[$fromCurrencyCode], $rates[$toCurrencyCode])) {
            throw new CurrencyConversionErrorException("Invalid currency conversion: $fromCurrencyCode to $toCurrencyCode");
        }

        // Since base is USD, we calculate:
        // amount in USD = amount / rate[from]
        // target amount = amount in USD * rate[to]

        $amountInUsd = $amount / $rates[$fromCurrencyCode];
        $convertedAmount = $amountInUsd * $rates[$toCurrencyCode];

        return MoneyValue::fromFloat($convertedAmount, $toCurrencyCode);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws CurrencyConversionErrorException
     */
    private function getRates(): array
    {
        $cacheKey = 'open_exchange_rates_latest';

        $rates = $this->cache->get($cacheKey);

        if ($rates === null) {
            $url = sprintf('%s?app_id=%s', self::API_URL, $this->apiKey);

            $response = file_get_contents($url);
            if ($response === false) {
                throw new CurrencyConversionErrorException('Failed to fetch exchange rates from Open Exchange Rates.');
            }

            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['rates']) || !is_array($data['rates'])) {
                throw new CurrencyConversionErrorException('Invalid response from Open Exchange Rates API.');
            }

            $rates = $data['rates'];
            $this->cache->set($cacheKey, $rates, self::CACHE_TTL);

            $this->logger->info('OpenExchangeRates: Cached latest rates.');
        }

        return $rates;
    }
}
