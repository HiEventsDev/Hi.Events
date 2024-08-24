<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Domain\CapacityAssignment\CreateCapacityAssignmentService;
use HiEvents\Services\Domain\CheckInList\CreateCheckInListService;
use HiEvents\Services\Domain\PromoCode\CreatePromoCodeService;
use HiEvents\Services\Domain\Question\CreateQuestionService;
use HiEvents\Services\Domain\Ticket\CreateTicketService;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DuplicateEventService
{
    public function __construct(
        private readonly EventRepositoryInterface        $eventRepository,
        private readonly CreateEventService              $createEventService,
        private readonly CreateTicketService             $createTicketService,
        private readonly CreateQuestionService           $createQuestionService,
        private readonly CreatePromoCodeService          $createPromoCodeService,
        private readonly CreateCapacityAssignmentService $createCapacityAssignmentService,
        private readonly CreateCheckInListService        $createCheckInListService,
        private readonly ImageRepositoryInterface        $imageRepository,
        private readonly DatabaseManager                 $databaseManager,
        private readonly HTMLPurifier                    $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function duplicateEvent(
        string  $eventId,
        string  $accountId,
        string  $title,
        string  $startDate,
        bool    $duplicateTickets = true,
        bool    $duplicateQuestions = true,
        bool    $duplicateSettings = true,
        bool    $duplicatePromoCodes = true,
        bool    $duplicateCapacityAssignments = true,
        bool    $duplicateCheckInLists = true,
        bool    $duplicateEventCoverImage = true,
        ?string $description = null,
        ?string $endDate = null,
    ): EventDomainObject
    {
        try {
            $this->databaseManager->beginTransaction();

            $event = $this->getEventWithRelations($eventId, $accountId);

            $event
                ->setTitle($title)
                ->setStartDate($startDate)
                ->setEndDate($endDate)
                ->setDescription($this->purifier->purify($description))
                ->setStatus(EventStatus::DRAFT->name);

            $newEvent = $this->cloneExistingEvent(
                event: $event,
                cloneEventSettings: $duplicateSettings,
            );

            if ($duplicateTickets) {
                $this->cloneExistingTickets(
                    event: $event,
                    newEventId: $newEvent->getId(),
                    duplicateQuestions: $duplicateQuestions,
                    duplicatePromoCodes: $duplicatePromoCodes,
                    duplicateCapacityAssignments: $duplicateCapacityAssignments,
                    duplicateCheckInLists: $duplicateCheckInLists,
                );
            }

            if ($duplicateEventCoverImage) {
                $this->cloneEventCoverImage($event, $newEvent->getId());
            }

            $this->databaseManager->commit();

            return $this->getEventWithRelations($newEvent->getId(), $newEvent->getAccountId());
        } catch (Throwable $e) {
            $this->databaseManager->rollBack();
            throw $e;
        }
    }

    /**
     * @param EventDomainObject $event
     * @param bool $cloneEventSettings
     * @return EventDomainObject
     * @throws Throwable
     */
    private function cloneExistingEvent(EventDomainObject $event, bool $cloneEventSettings): EventDomainObject
    {
        return $this->createEventService->createEvent(
            eventData: (new EventDomainObject())
                ->setOrganizerId($event->getOrganizerId())
                ->setAccountId($event->getAccountId())
                ->setUserId($event->getUserId())
                ->setTitle($event->getTitle())
                ->setStartDate($event->getStartDate())
                ->setEndDate($event->getEndDate())
                ->setDescription($event->getDescription())
                ->setAttributes($event->getAttributes())
                ->setTimezone($event->getTimezone())
                ->setCurrency($event->getCurrency())
                ->setStatus($event->getStatus()),
            eventSettings: $cloneEventSettings ? $event->getEventSettings() : null,
        );
    }

    /**
     * @throws Throwable
     */
    private function cloneExistingTickets(
        EventDomainObject $event,
        int               $newEventId,
        bool              $duplicateQuestions,
        bool              $duplicatePromoCodes,
        bool              $duplicateCapacityAssignments,
        bool              $duplicateCheckInLists,
    ): array
    {
        $oldTicketToNewTicketMap = [];

        foreach ($event->getTickets() as $ticket) {
            $ticket->setEventId($newEventId);
            $newTicket = $this->createTicketService->createTicket(
                ticket: $ticket,
                accountId: $event->getAccountId(),
                taxAndFeeIds: $ticket->getTaxAndFees()?->map(fn($taxAndFee) => $taxAndFee->getId())?->toArray(),
            );
            $oldTicketToNewTicketMap[$ticket->getId()] = $newTicket->getId();
        }

        if ($duplicateQuestions) {
            $this->cloneQuestions($event, $newEventId, $oldTicketToNewTicketMap);
        }

        if ($duplicatePromoCodes) {
            $this->clonePromoCodes($event, $newEventId, $oldTicketToNewTicketMap);
        }

        if ($duplicateCapacityAssignments) {
            $this->cloneCapacityAssignments($event, $newEventId, $oldTicketToNewTicketMap);
        }

        if ($duplicateCheckInLists) {
            $this->cloneCheckInLists($event, $newEventId, $oldTicketToNewTicketMap);
        }

        return $oldTicketToNewTicketMap;
    }

    /**
     * @throws Throwable
     */
    private function cloneQuestions(EventDomainObject $event, int $newEventId, array $oldTicketToNewTicketMap): void
    {
        foreach ($event->getQuestions() as $question) {
            $this->createQuestionService->createQuestion(
                (new QuestionDomainObject())
                    ->setTitle($question->getTitle())
                    ->setEventId($newEventId)
                    ->setBelongsTo($question->getBelongsTo())
                    ->setType($question->getType())
                    ->setRequired($question->getRequired())
                    ->setOptions($question->getOptions())
                    ->setIsHidden($question->getIsHidden()),
                array_map(
                    static fn(TicketDomainObject $ticket) => $oldTicketToNewTicketMap[$ticket->getId()],
                    $question->getTickets()?->all(),
                ),
            );
        }
    }

    /**
     * @throws Throwable
     */
    private function clonePromoCodes(EventDomainObject $event, int $newEventId, array $oldTicketToNewTicketMap): void
    {
        foreach ($event->getPromoCodes() as $promoCode) {
            $this->createPromoCodeService->createPromoCode(
                (new PromoCodeDomainObject())
                    ->setCode($promoCode->getCode())
                    ->setEventId($newEventId)
                    ->setApplicableTicketIds(array_map(
                        static fn($ticketId) => $oldTicketToNewTicketMap[$ticketId],
                        $promoCode->getApplicableTicketIds() ?? [],
                    ))
                    ->setDiscountType($promoCode->getDiscountType())
                    ->setDiscount($promoCode->getDiscount())
                    ->setExpiryDate($promoCode->getExpiryDate())
                    ->setMaxAllowedUsages($promoCode->getMaxAllowedUsages()),
            );
        }
    }

    private function cloneCapacityAssignments(EventDomainObject $event, int $newEventId, $oldTicketToNewTicketMap): void
    {
        /** @var CapacityAssignmentDomainObject $capacityAssignment */
        foreach ($event->getCapacityAssignments() as $capacityAssignment) {
            $this->createCapacityAssignmentService->createCapacityAssignment(
                capacityAssignment: (new CapacityAssignmentDomainObject())
                    ->setName($capacityAssignment->getName())
                    ->setEventId($newEventId)
                    ->setCapacity($capacityAssignment->getCapacity())
                    ->setAppliesTo($capacityAssignment->getAppliesTo())
                    ->setStatus($capacityAssignment->getStatus()),
                ticketIds: $capacityAssignment->getTickets()
                ?->map(fn($ticket) => $oldTicketToNewTicketMap[$ticket->getId()])?->toArray() ?? [],
            );
        }
    }

    private function cloneCheckInLists(EventDomainObject $event, int $newEventId, $oldTicketToNewTicketMap): void
    {
        foreach ($event->getCheckInLists() as $checkInList) {
            $this->createCheckInListService->createCheckInList(
                checkInList: (new CheckInListDomainObject())
                    ->setName($checkInList->getName())
                    ->setDescription($checkInList->getDescription())
                    ->setExpiresAt($checkInList->getExpiresAt())
                    ->setActivatesAt($checkInList->getActivatesAt())
                    ->setEventId($newEventId),
                ticketIds: $checkInList->getTickets()
                ?->map(fn($ticket) => $oldTicketToNewTicketMap[$ticket->getId()])?->toArray() ?? [],
            );
        }
    }

    private function cloneEventCoverImage(EventDomainObject $event, int $newEventId): void
    {
        /** @var ImageDomainObject $coverImage */
        $coverImage = $event->getImages()?->first(fn(ImageDomainObject $image) => $image->getType() === EventImageType::EVENT_COVER->name);
        if ($coverImage) {
            $this->imageRepository->create([
                'entity_id' => $newEventId,
                'entity_type' => EventDomainObject::class,
                'type' => EventImageType::EVENT_COVER->name,
                'disk' => $coverImage->getDisk(),
                'path' => $coverImage->getPath(),
                'filename' => $coverImage->getFileName(),
                'size' => $coverImage->getSize(),
                'mime_type' => $coverImage->getMimeType(),
            ]);
        }
    }

    private function getEventWithRelations(string $eventId, string $accountId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(
                new Relationship(TicketDomainObject::class, [
                    new Relationship(TicketPriceDomainObject::class),
                    new Relationship(TaxAndFeesDomainObject::class)
                ])
            )
            ->loadRelation(PromoCodeDomainObject::class)
            ->loadRelation(new Relationship(QuestionDomainObject::class, [
                new Relationship(TicketDomainObject::class),
            ]))
            ->loadRelation(new Relationship(CapacityAssignmentDomainObject::class, [
                new Relationship(TicketDomainObject::class),
            ]))
            ->loadRelation(new Relationship(CheckInListDomainObject::class, [
                new Relationship(TicketDomainObject::class),
            ]))
            ->loadRelation(ImageDomainObject::class)
            ->findFirstWhere([
                'id' => $eventId,
                'account_id' => $accountId,
            ]);
    }
}
