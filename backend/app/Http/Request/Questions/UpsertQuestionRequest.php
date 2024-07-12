<?php

namespace HiEvents\Http\Request\Questions;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertQuestionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['string', 'required'],
            'description' => ['string', 'nullable', 'max:10000'],
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
