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
            'product_ids' => ['array', 'required_if:belongs_to,PRODUCT'],
            'belongs_to' => [
                ['required', Rule::in([QuestionBelongsTo::PRODUCT->name, QuestionBelongsTo::ORDER->name])],
            ],
            'options' => 'max:2000|required_if:type,CHECKBOX,RADIO',
            'required' => 'required|boolean',
            'is_hidden' => 'required|boolean',
            'conditions' => ['nullable', 'array'],
            'conditions.parent_question_id' => ['required_with:conditions', 'integer'],
            'conditions.condition_value' => ['required_with:conditions'],
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.min_length' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'validation_rules.max_length' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'validation_rules.placeholder' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required_if' => __('Please select at least one product.'),
        ];
    }
}
