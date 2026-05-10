<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Resources\CheckInList\AttendeeCheckInPublicResource;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\PublicAttendeeDetailDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicAttendeeDetailDTO
 */
class AttendeeDetailPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attendee = $this->attendee;
        $order = $attendee->getOrder();
        $product = $attendee->getProduct();

        $occurrence = $attendee->getEventOccurrence();

        $data = [
            'id' => $attendee->getId(),
            'public_id' => $attendee->getPublicId(),
            'first_name' => $attendee->getFirstName(),
            'last_name' => $attendee->getLastName(),
            'email' => $attendee->getEmail(),
            'status' => $attendee->getStatus(),
            'product_id' => $attendee->getProductId(),
            'product_title' => $product?->getTitle(),
            'event_occurrence' => $occurrence
                ? (new EventOccurrenceResourcePublic($occurrence))->toArray(request())
                : null,
            'check_ins' => $this->currentListCheckIns
                ->map(static fn(AttendeeCheckInDomainObject $checkIn) => (new AttendeeCheckInPublicResource($checkIn))->toArray(request()))
                ->values()
                ->all(),
            'visibility' => [
                'notes' => $this->showNotes,
                'question_answers' => $this->showQuestionAnswers,
                'order_details' => $this->showOrderDetails,
            ],
        ];

        if ($this->showNotes) {
            $data['notes'] = $attendee->getNotes();
        }

        if ($this->showQuestionAnswers) {
            $data['question_answers'] = array_map(
                static fn(QuestionAndAnswerViewDomainObject $qa) => [
                    'question_id' => $qa->getQuestionId(),
                    'title' => $qa->getTitle(),
                    'answer' => $qa->getAnswer(),
                    'belongs_to' => $qa->getBelongsTo(),
                ],
                $attendee->getQuestionAndAnswerViews()?->all() ?? [],
            );
        }

        if ($this->showOrderDetails && $order) {
            $data['order'] = [
                'id' => $order->getId(),
                'public_id' => $order->getPublicId(),
                'short_id' => $order->getShortId(),
                'status' => $order->getStatus(),
                'total_gross' => $order->getTotalGross(),
                'currency' => $order->getCurrency(),
                'first_name' => $order->getFirstName(),
                'last_name' => $order->getLastName(),
                'email' => $order->getEmail(),
                'created_at' => $order->getCreatedAt(),
            ];
        }

        return $data;
    }
}
