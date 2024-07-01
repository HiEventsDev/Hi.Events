<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\PromoCode\CreatePromoCodeService;
use HiEvents\Services\Domain\Question\CreateQuestionService;
use HiEvents\Services\Domain\Ticket\CreateTicketService;
use Illuminate\Support\Collection;
use Throwable;

class DuplicateEventService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CreateEventService       $createEventService,
        private readonly CreateTicketService      $createTicketService,
        private readonly CreateQuestionService    $createQuestionService,
        private readonly CreatePromoCodeService   $createPromoCodeService,
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
        ?string $description = null,
        ?string $endDate = null,
    ): EventDomainObject
    {
        $event = $this->getEventWithRelations($eventId, $accountId);

        $event
            ->setTitle($title)
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setDescription($description);

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
            );
        }

        return $this->getEventWithRelations($newEvent->getId(), $newEvent->getAccountId());
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
            eventSettings: $cloneEventSettings
                ? $event->getEventSettings()
                : null,
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
    ): void
    {
        $oldTicketToNewTicketMap = [];

        $tickets = $event->getTickets();
        foreach ($tickets as $ticket) {
            $ticket->setEventId($newEventId);
            $newTicket = $this->createTicketService->createTicket(
                $ticket,
                $event->getAccountId(),
            );

            $oldTicketToNewTicketMap[$ticket->getId()] = $newTicket->getId();
        }

        if ($duplicateQuestions) {
            /** @var Collection<QuestionDomainObject> $questions */
            $questions = $event->getQuestions();

            foreach ($questions as $question) {
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

        if ($duplicatePromoCodes) {
            /** @var Collection<PromoCodeDomainObject> $promoCodes */
            $promoCodes = $event->getPromoCodes();

            foreach ($promoCodes as $promoCode) {
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
    }

    private function getEventWithRelations(string $eventId, string $accountId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(TicketDomainObject::class)
            ->loadRelation(PromoCodeDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: QuestionDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: TicketDomainObject::class,
                    ),
                ]))
            ->loadRelation(new Relationship(
                domainObject: TicketDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: TicketPriceDomainObject::class,
                    ),
                ]))
            ->findFirstWhere([
                'id' => $eventId,
                'account_id' => $accountId,
            ]);
    }
}
