<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TicketTransferBlock;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferStateDTO;
use Illuminate\Support\Collection;

class TicketTransferStateService
{
    public function __construct(
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly TicketTransferEligibilityService $eligibilityService,
        private readonly TicketResaleListingRepositoryInterface $ticketResaleListingRepository,
    ) {}

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     */
    public function describe(Collection $attendees, OrderDomainObject $order, EventDomainObject $event): void
    {
        $describable = $attendees->filter(
            fn (AttendeeDomainObject $attendee) => $attendee->getProduct() !== null && $attendee->getEventOccurrence() !== null
        );

        if ($describable->isEmpty() || $event->getEventSettings() === null) {
            return;
        }

        $attendeeIds = $describable->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->values()->all();

        $transfersByAttendee = $this->ticketTransferRepository
            ->findWhereIn('attendee_id', $attendeeIds)
            ->groupBy(fn (TicketTransferDomainObject $transfer) => $transfer->getAttendeeId());

        $listedAttendeeIds = $this->ticketResaleListingRepository
            ->findWhereIn(TicketResaleListingDomainObjectAbstract::ATTENDEE_ID, $attendeeIds, [
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
            ])
            ->map(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId());

        $describable->each(function (AttendeeDomainObject $attendee) use ($order, $event, $transfersByAttendee, $listedAttendeeIds): void {
            $context = new TicketTransferContextDTO(
                event: $event,
                order: $order,
                attendee: $attendee,
                product: $attendee->getProduct(),
                occurrence: $attendee->getEventOccurrence(),
                transfers: $transfersByAttendee->get($attendee->getId(), collect()),
                is_checked_in: $attendee->isCheckedIn(),
                has_open_listing: $listedAttendeeIds->contains($attendee->getId()),
            );

            $block = $this->eligibilityService->blockOnStarting($context);

            $attendee->setTransferState(new TicketTransferStateDTO(
                can_start: $block === null,
                blocked_reason: $block === null || $block === TicketTransferBlock::TRANSFER_PENDING ? null : $block->message(),
                pending: $context->pendingTransfer(),
            ));
        });
    }
}
