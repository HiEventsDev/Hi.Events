<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalContextDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\Terminal\Location;

class StripeTerminalLocationService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly TerminalLocationAddress $locationAddress,
    ) {}

    /**
     * @throws TerminalLocationAddressMissingException
     */
    public function resolve(OrganizerDomainObject $organizer, TerminalContextDTO $context): string
    {
        $existing = $this->retrieve($organizer, $context);

        if ($existing === null) {
            return $this->create($organizer, $context)->id;
        }

        $desired = $this->locationAddress->fromOrganizer($organizer);
        $current = $existing->address?->toArray();

        if ($this->locationAddress->matches($current, $desired)) {
            return $existing->id;
        }

        if (! $this->locationAddress->sameCountry($current, $desired)) {
            return $this->create($organizer, $context)->id;
        }

        $context->client->terminal->locations->update($existing->id, [
            'display_name' => $organizer->getName(),
            'address' => $desired,
        ], $context->requestOptions());

        return $existing->id;
    }

    private function retrieve(OrganizerDomainObject $organizer, TerminalContextDTO $context): ?Location
    {
        if ($organizer->getStripeTerminalLocationId() === null) {
            return null;
        }

        try {
            $location = $context->client->terminal->locations->retrieve($organizer->getStripeTerminalLocationId(), [], $context->requestOptions());
        } catch (InvalidRequestException) {
            return null;
        }

        return ($location->deleted ?? false) ? null : $location;
    }

    /**
     * @throws TerminalLocationAddressMissingException
     */
    private function create(OrganizerDomainObject $organizer, TerminalContextDTO $context): Location
    {
        $location = $context->client->terminal->locations->create([
            'display_name' => $organizer->getName(),
            'address' => $this->locationAddress->fromOrganizer($organizer),
        ], $context->requestOptions());

        $this->organizerRepository->updateFromArray($organizer->getId(), [
            OrganizerDomainObjectAbstract::STRIPE_TERMINAL_LOCATION_ID => $location->id,
        ]);

        return $location;
    }
}
