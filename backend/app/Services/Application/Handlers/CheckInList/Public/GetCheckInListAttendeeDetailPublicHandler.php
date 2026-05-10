<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\PublicAttendeeDetailDTO;
use Illuminate\Support\Collection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListAttendeeDetailPublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface    $attendeeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    )
    {
    }

    public function handle(string $shortId, string $attendeePublicId, ?int $staffAccountId): PublicAttendeeDetailDTO
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findFirstWhere([
                CheckInListDomainObjectAbstract::SHORT_ID => $shortId,
            ]);

        if (!$checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->validateCheckInListIsActive($checkInList);

        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
            ->loadRelation(QuestionAndAnswerViewDomainObject::class)
            ->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'))
            ->loadRelation(new Relationship(AttendeeCheckInDomainObject::class, name: 'check_ins'))
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findFirstWhere([
                'public_id' => $attendeePublicId,
                'event_id' => $checkInList->getEventId(),
            ]);

        if (!$attendee) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $this->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        $currentListCheckIns = $this->filterCheckInsForList($attendee->getCheckIns(), $checkInList->getId());
        $isStaff = $this->hasStaffAccess($checkInList, $staffAccountId);

        return new PublicAttendeeDetailDTO(
            attendee: $attendee,
            currentListCheckIns: $currentListCheckIns,
            showNotes: $isStaff || $checkInList->getPublicShowAttendeeNotes(),
            showQuestionAnswers: $isStaff || $checkInList->getPublicShowQuestionAnswers(),
            showOrderDetails: $isStaff || $checkInList->getPublicShowOrderDetails(),
        );
    }

    /**
     * @return Collection<int, AttendeeCheckInDomainObject>
     */
    private function filterCheckInsForList(?Collection $checkIns, int $checkInListId): Collection
    {
        if ($checkIns === null) {
            return new Collection();
        }

        return $checkIns->filter(
            static fn(AttendeeCheckInDomainObject $checkIn) => $checkIn->getCheckInListId() === $checkInListId
        )->values();
    }

    private function hasStaffAccess(CheckInListDomainObject $checkInList, ?int $staffAccountId): bool
    {
        if ($staffAccountId === null) {
            return false;
        }

        $event = $checkInList->getEvent();
        if ($event === null) {
            return false;
        }

        return $event->getAccountId() === $staffAccountId;
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsActive(CheckInListDomainObject $checkInList): void
    {
        if ($checkInList->getExpiresAt() && DateHelper::utcDateIsPast($checkInList->getExpiresAt())) {
            throw new CannotCheckInException(__('Check-in list has expired'));
        }

        if ($checkInList->getActivatesAt() && DateHelper::utcDateIsFuture($checkInList->getActivatesAt())) {
            throw new CannotCheckInException(__('Check-in list is not active yet'));
        }
    }

    private function verifyAttendeeBelongsToCheckInList(
        CheckInListDomainObject $checkInList,
        AttendeeDomainObject $attendee,
    ): void {
        $allowedProductIds = $checkInList->getProducts()?->map(fn($product) => $product->getId())->toArray() ?? [];

        if (! empty($allowedProductIds) && ! in_array($attendee->getProductId(), $allowedProductIds, true)) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        if ($checkInList->getEventOccurrenceId() !== null
            && $attendee->getEventOccurrenceId() !== $checkInList->getEventOccurrenceId()
        ) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }
    }
}
