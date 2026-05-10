<?php

namespace HiEvents\Services\Domain\CheckInList;

use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Illuminate\Support\Collection;

class CheckInListDataService
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly AttendeeRepositoryInterface    $attendeeRepository,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     */
    public function verifyAttendeeBelongsToCheckInList(
        CheckInListDomainObject $checkInList,
        AttendeeDomainObject    $attendee,
    ): void
    {
        $allowedProductIds = $checkInList->getProducts()?->map(fn($product) => $product->getId())->toArray() ?? [];

        // A list with zero product attachments covers every ticket on the event;
        // we only reject when it has specific product scope AND the attendee's
        // product isn't in it.
        if (!empty($allowedProductIds) && !in_array($attendee->getProductId(), $allowedProductIds, true)) {
            throw new CannotCheckInException(
                __('Attendee :attendee_name is not allowed to check in using this check-in list', [
                    'attendee_name' => $attendee->getFullName(),
                ])
            );
        }

        // Belt-and-braces when the list covers all tickets: the attendee's
        // event must match the list's event. Normally the data model already
        // guarantees this via the product FK, but for the empty-attachments
        // case we have no product chain to rely on.
        if (empty($allowedProductIds) && $attendee->getEventId() !== $checkInList->getEventId()) {
            throw new CannotCheckInException(
                __('Attendee :attendee_name does not belong to this event', [
                    'attendee_name' => $attendee->getFullName(),
                ])
            );
        }

        if ($checkInList->getEventOccurrenceId() !== null
            && $attendee->getEventOccurrenceId() !== $checkInList->getEventOccurrenceId()
        ) {
            throw new CannotCheckInException(
                __(':attendee_name\'s ticket is for a different session — check they\'re on the right check-in list.', [
                    'attendee_name' => $attendee->getFullName(),
                ])
            );
        }
    }

    /**
     * @return Collection<AttendeeDomainObject>
     * @throws Exception
     *
     * @throws CannotCheckInException
     */
    public function getAttendees(Collection $attendeePublicIds): Collection
    {
        $attendeePublicIds = array_unique($attendeePublicIds->toArray());

        $attendees = $this->attendeeRepository->findWhereIn(
            field: AttendeeDomainObjectAbstract::PUBLIC_ID,
            values: $attendeePublicIds
        );

        if (count($attendees) !== count($attendeePublicIds)) {
            throw new CannotCheckInException(__('Invalid attendee code detected: :attendees ', [
                'attendees' => implode(', ', array_diff(
                        $attendeePublicIds,
                        $attendees->pluck(AttendeeDomainObjectAbstract::PUBLIC_ID)->toArray())
                ),
            ]));
        }

        return $attendees;
    }

    /**
     * @throws CannotCheckInException
     */
    public function getCheckInList(string $checkInListUuid): CheckInListDomainObject
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([
                CheckInListDomainObjectAbstract::SHORT_ID => $checkInListUuid,
            ]);

        if ($checkInList === null) {
            throw new CannotCheckInException(__('Check-in list not found'));
        }

        return $checkInList;
    }
}
