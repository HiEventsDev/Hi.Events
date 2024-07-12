<?php

namespace HiEvents\Resources\Question;

use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionAndAnswerViewDomainObject
 */
class QuestionAnswerViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->getQuestionId(),
            'title' => $this->getTitle(),
            'answer' => $this->getAnswer(),
            'text_answer' => app(QuestionAnswerFormatter::class)->getAnswerAsText(
                $this->getAnswer(),
                QuestionTypeEnum::fromName($this->getQuestionType())
            ),
            'order_id' => $this->getOrderId(),
            'belongs_to' => $this->getBelongsTo(),
            'question_type' => $this->getQuestionType(),

            $this->mergeWhen(
                $this->getAttendeeId() !== null,
                fn() => [
                    'attendee_id' => $this->getAttendeeId(),
                    'first_name' => $this->getFirstName(),
                    'last_name' => $this->getLastName(),
                ]
            ),
        ];
    }
}
