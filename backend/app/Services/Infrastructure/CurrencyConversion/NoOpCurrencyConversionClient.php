<?php

namespace HiEvents\Services\Infrastructure\CurrencyConversion;

use Brick\Money\Currency;
use HiEvents\Values\MoneyValue;
use Psr\Log\LoggerInterface;

class NoOpCurrencyConversionClient implements CurrencyConversionClientInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function convert(Currency $fromCurrency, Currency $toCurrency, float $amount): MoneyValue
    {
        $this->logger->warning(
            'NoOpCurrencyConversionClient is being used. This should only be used as a last resort fallback. Never in production.',
            [
                'fromCurrency' => $fromCurrency->getCurrencyCode(),
                'toCurrency' => $toCurrency->getCurrencyCode(),
                'amount' => $amount,
            ]
        );

        return MoneyValue::fromFloat($amount, $toCurrency->getCurrencyCode());
    }
}
