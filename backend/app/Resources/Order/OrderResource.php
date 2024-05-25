<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Question\QuestionAnswerViewResource;
use Illuminate\Http\Request;

/**
 * @mixin OrderDomainObject
 */
class OrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'total_gross' => $this->getTotalGross(),
            'total_tax' => $this->getTotalTax(),
            'total_fee' => $this->getTotalFee(),
            'total_refunded' => $this->getTotalRefunded(),
            'status' => $this->getStatus(),
            'refund_status' => $this->getRefundStatus(),
            'payment_status' => $this->getPaymentStatus(),
            'currency' => $this->getCurrency(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'created_at' => $this->getCreatedAt(),
            'public_id' => $this->getPublicId(),
            'payment_gateway' => $this->getPaymentGateway(),
            'is_partially_refunded' => $this->isPartiallyRefunded(),
            'is_fully_refunded' => $this->isFullyRefunded(),
            'is_free_order' => $this->isFreeOrder(),
            'is_manually_created' => $this->getIsManuallyCreated(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
            'order_items' => $this->when(
                !is_null($this->getOrderItems()),
                fn() => OrderItemResource::collection($this->getOrderItems())
            ),
            'attendees' => $this->when(
                !is_null($this->getAttendees()),
                fn() => AttendeeResource::collection($this->getAttendees())
            ),
            'question_answers' => $this->when(
                !is_null($this->getQuestionAndAnswerViews()),
                fn() => QuestionAnswerViewResource::collection(
                    $this->getQuestionAndAnswerViews()
                        ?->filter(fn($qav) => $qav->getBelongsTo() === QuestionBelongsTo::ORDER->name)
                )
            ),
        ];
    }
}
