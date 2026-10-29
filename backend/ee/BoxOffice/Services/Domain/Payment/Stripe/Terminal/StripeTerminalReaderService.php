<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\Generated\StripeTerminalReaderDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalContextDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReadersResponseDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Terminal\Reader;

class StripeTerminalReaderService
{
    private const STATUS_UNKNOWN = 'unknown';

    public function __construct(
        private readonly StripeTerminalReaderRepositoryInterface $readerRepository,
        private readonly StripeTerminalLocationService $locationService,
        private readonly StripeTerminalContextService $contextService,
        private readonly LoggerInterface $logger,
    ) {}

    public function listForOrganizer(OrganizerDomainObject $organizer): TerminalReadersResponseDTO
    {
        $readers = $this->readerRepository->findWhere([StripeTerminalReaderDomainObjectAbstract::ORGANIZER_ID => $organizer->getId()]);

        try {
            $context = $this->contextService->forOrganizer($organizer);
        } catch (StripeClientConfigurationException) {
            return new TerminalReadersResponseDTO(readers: collect(), stripe_configured: false, stripe_connected: false);
        } catch (ResourceConflictException) {
            return new TerminalReadersResponseDTO(readers: collect(), stripe_configured: true, stripe_connected: false);
        }

        $liveStatuses = $this->fetchLiveStatuses($context);

        return new TerminalReadersResponseDTO(
            readers: $readers->map(function (StripeTerminalReaderDomainObject $reader) use ($context, $liveStatuses, $organizer) {
                $live = $liveStatuses[$reader->getStripeReaderId()] ?? null;
                $isAvailable = $reader->getOrganizerStripePlatformId() === $context->organizer_stripe_platform_id
                    && ($live === null || $this->onCurrentLocation($organizer, $live));

                return new TerminalReaderDTO(
                    id: $reader->getId(),
                    label: $reader->getLabel(),
                    device_type: $live?->device_type,
                    status: $isAvailable ? ($live?->status ?? self::STATUS_UNKNOWN) : 'unavailable',
                    is_available: $isAvailable,
                );
            })->values(),
            stripe_configured: true,
            stripe_connected: true,
        );
    }

    /**
     * @throws ApiErrorException
     * @throws ResourceConflictException
     * @throws StripeClientConfigurationException
     * @throws TerminalLocationAddressMissingException
     */
    public function register(OrganizerDomainObject $organizer, string $registrationCode, string $label): StripeTerminalReaderDomainObject
    {
        $context = $this->contextService->forOrganizer($organizer);
        $locationId = $this->locationService->resolve($organizer, $context);

        $reader = $context->client->terminal->readers->create([
            'registration_code' => $registrationCode,
            'label' => $label,
            'location' => $locationId,
        ], $context->requestOptions());

        return $this->readerRepository->create([
            StripeTerminalReaderDomainObjectAbstract::ORGANIZER_ID => $organizer->getId(),
            StripeTerminalReaderDomainObjectAbstract::ORGANIZER_STRIPE_PLATFORM_ID => $context->organizer_stripe_platform_id,
            StripeTerminalReaderDomainObjectAbstract::STRIPE_READER_ID => $reader->id,
            StripeTerminalReaderDomainObjectAbstract::LABEL => $label,
        ]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function delete(OrganizerDomainObject $organizer, int $readerId): void
    {
        $reader = $this->readerRepository->findFirstWhere([
            StripeTerminalReaderDomainObjectAbstract::ID => $readerId,
            StripeTerminalReaderDomainObjectAbstract::ORGANIZER_ID => $organizer->getId(),
        ]);

        if ($reader === null) {
            throw new ResourceNotFoundException(__('Card reader not found'));
        }

        try {
            $context = $this->contextService->forOrganizer($organizer);
            $this->cancelInProgressAction($context, $reader->getStripeReaderId());
            $context->client->terminal->readers->delete($reader->getStripeReaderId(), [], $context->requestOptions());
        } catch (ApiErrorException|StripeClientConfigurationException|ResourceConflictException $exception) {
            $this->logger->warning('Stripe terminal reader could not be deleted from Stripe', [
                'reader_id' => $reader->getStripeReaderId(),
                'message' => $exception->getMessage(),
            ]);
        }

        $this->readerRepository->deleteWhere([StripeTerminalReaderDomainObjectAbstract::ID => $reader->getId()]);
    }

    private function onCurrentLocation(OrganizerDomainObject $organizer, Reader $live): bool
    {
        $locationId = is_object($live->location) ? $live->location->id : $live->location;

        return $organizer->getStripeTerminalLocationId() === null || $locationId === $organizer->getStripeTerminalLocationId();
    }

    private function cancelInProgressAction(TerminalContextDTO $context, string $stripeReaderId): void
    {
        try {
            $context->client->terminal->readers->cancelAction($stripeReaderId, [], $context->requestOptions());
        } catch (ApiErrorException) {
        }
    }

    /**
     * @return array<string, Reader>
     */
    private function fetchLiveStatuses(TerminalContextDTO $context): array
    {
        try {
            $readers = $context->client->terminal->readers->all(['limit' => 100], $context->requestOptions());
        } catch (ApiErrorException $exception) {
            $this->logger->warning('Unable to fetch Stripe terminal reader statuses', ['message' => $exception->getMessage()]);

            return [];
        }

        $statuses = [];
        foreach ($readers->data as $reader) {
            $statuses[$reader->id] = $reader;
        }

        return $statuses;
    }
}
