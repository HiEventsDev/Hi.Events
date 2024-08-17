<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Resources\Question\QuestionAnswerViewResource;
use HiEvents\Resources\Ticket\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'ticket_id' => $this->getTicketId(),
            'ticket_price_id' => $this->getTicketPriceId(),
            'event_id' => $this->getEventId(),
            'email' => $this->getEmail(),
            'status' => $this->getStatus(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'public_id' => $this->getPublicId(),
            'short_id' => $this->getShortId(),
            'locale' => $this->getLocale(),
            'ticket' => $this->when(
                !is_null($this->getTicket()),
                fn() => new TicketResource($this->getTicket()),
            ),
            'order' => $this->when(
                !is_null($this->getOrder()),
                fn() => new OrderResource($this->getOrder())
            ),
            'question_answers' => $this->when(
                $this->getQuestionAndAnswerViews() !== null,
                fn() => QuestionAnswerViewResource::collection(
                    $this->getQuestionAndAnswerViews()
                        ?->filter(fn($qav) => $qav->getBelongsTo() === QuestionBelongsTo::TICKET->name)
                )
            ),

            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }

}
