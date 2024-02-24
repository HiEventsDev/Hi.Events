<?php

namespace HiEvents\Http\Request\Questions;

use Illuminate\Validation\Rule;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\Http\Request\BaseRequest;

class UpsertQuestionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['string', 'required'],
            'type' => ['required', Rule::in(QuestionTypeEnum::valuesArray())],
            'ticket_ids' => ['array', 'required_if:belongs_to,TICKET'],
            'belongs_to' => [
                ['required', Rule::in([QuestionBelongsTo::TICKET->name, QuestionBelongsTo::ORDER->name])],
            ],
            'options' => 'max:2000|required_if:type,CHECKBOX,RADIO',
            'required' => 'required|boolean',
            'is_hidden' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_ids.required_if' => __('Please select at least one ticket.'),
        ];
    }
}
