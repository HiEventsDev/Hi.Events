<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TicketTransferClaimDTO;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferContextService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferEligibilityService;

class GetTicketTransferClaimHandler
{
    public function __construct(
        private readonly TicketTransferClaimService $claimService,
        private readonly TicketTransferContextService $contextService,
        private readonly TicketTransferEligibilityService $eligibilityService,
        private readonly QuestionRepositoryInterface $questionRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(string $token): TicketTransferClaimDTO
    {
        $transfer = $this->claimService->findByToken($token);
        $context = $this->contextService->forAttendeeId($transfer->getAttendeeId());
        $canAccept = $transfer->isAwaitingRecipient() && $this->eligibilityService->blockOnHolderChange($context) === null;

        return new TicketTransferClaimDTO(
            transfer: $transfer,
            context: $context,
            can_accept: $canAccept,
            questions: $canAccept ? $this->attendeeQuestionsFor($context->event->getId(), $context->product->getId()) : collect(),
        );
    }

    private function attendeeQuestionsFor(int $eventId, int $productId)
    {
        return $this->questionRepository
            ->loadRelation(new Relationship(ProductDomainObject::class))
            ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->filter(fn (QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::PRODUCT->name
                && ! $question->getIsHidden()
                && $question->getProducts()?->contains(fn (ProductDomainObject $product) => $product->getId() === $productId))
            ->sortBy(fn (QuestionDomainObject $question) => $question->getOrder())
            ->values();
    }
}
