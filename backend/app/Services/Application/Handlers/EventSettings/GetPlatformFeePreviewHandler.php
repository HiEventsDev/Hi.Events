<?php

namespace HiEvents\Services\Application\Handlers\EventSettings;

use Brick\Money\Currency;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\GetPlatformFeePreviewDTO;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PlatformFeePreviewResponseDTO;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;

class GetPlatformFeePreviewHandler
{
    public function __construct(
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly CurrencyConversionClientInterface $currencyConversionClient,
    )
    {
    }

    public function handle(GetPlatformFeePreviewDTO $dto): PlatformFeePreviewResponseDTO
    {
        /** @var EventDomainObject $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: OrganizerConfigurationDomainObject::class,
                        name: 'organizer_configuration',
                    ),
                ],
                name: 'organizer',
            ))
            ->findById($dto->eventId);

        $eventCurrency = $event->getCurrency();
        $configuration = $event->getOrganizer()?->getOrganizerConfiguration();

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
