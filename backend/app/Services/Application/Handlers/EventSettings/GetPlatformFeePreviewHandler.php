<?php

namespace HiEvents\Services\Application\Handlers\EventSettings;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\GetPlatformFeePreviewDTO;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PlatformFeePreviewResponseDTO;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;

class GetPlatformFeePreviewHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface        $accountRepository,
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly CurrencyConversionClientInterface $currencyConversionClient,
    )
    {
    }

    public function handle(GetPlatformFeePreviewDTO $dto): PlatformFeePreviewResponseDTO
    {
        $event = $this->eventRepository->findById($dto->eventId);
        $eventCurrency = $event->getCurrency();

        $account = $this->accountRepository
            ->loadRelation(new Relationship(
                domainObject: AccountConfigurationDomainObject::class,
                name: 'configuration',
            ))
            ->findByEventId($dto->eventId);

        $configuration = $account->getConfiguration();

        if ($configuration === null) {
            return new PlatformFeePreviewResponseDTO(
                eventCurrency: $eventCurrency,
                feeCurrency: null,
                fixedFeeOriginal: 0,
                fixedFeeConverted: 0,
                percentageFee: 0,
                samplePrice: $dto->price,
                platformFee: 0,
                total: $dto->price,
            );
        }

        $feeCurrency = $configuration->getApplicationFeeCurrency();
        $fixedFeeOriginal = $configuration->getFixedApplicationFee();
        $percentageFee = $configuration->getPercentageApplicationFee();

        $fixedFeeConverted = $this->convertFixedFee($fixedFeeOriginal, $feeCurrency, $eventCurrency);

        $platformFee = $this->calculatePlatformFee($fixedFeeConverted, $percentageFee, $dto->price);

        return new PlatformFeePreviewResponseDTO(
            eventCurrency: $eventCurrency,
            feeCurrency: $feeCurrency,
            fixedFeeOriginal: $fixedFeeOriginal,
            fixedFeeConverted: round($fixedFeeConverted, 2),
            percentageFee: $percentageFee,
            samplePrice: $dto->price,
            platformFee: $platformFee,
            total: round($dto->price + $platformFee, 2),
        );
    }

    private function convertFixedFee(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        return $this->currencyConversionClient->convert(
            fromCurrency: Currency::of($fromCurrency),
            toCurrency: Currency::of($toCurrency),
            amount: $amount
        )->toFloat();
    }

    private function calculatePlatformFee(float $fixedFee, float $percentageFee, float $price): float
    {
        $percentageRate = $percentageFee / 100;

        $platformFee = $percentageRate >= 1
            ? $fixedFee + ($price * $percentageRate)
            : ($fixedFee + ($price * $percentageRate)) / (1 - $percentageRate);

        return round($platformFee, 2);
    }
}
