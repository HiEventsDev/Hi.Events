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
            'product_ids' => ['array', Rule::requiredIf(fn () => $this->input('belongs_to') === QuestionBelongsTo::PRODUCT->name
                && ! $this->boolean('applies_to_all_tickets'))],
            'applies_to_all_tickets' => ['sometimes', 'boolean'],
            'condition_question_id' => ['nullable', 'integer'],
            /** @var string[]|null */
            'condition_values' => ['nullable', 'array', 'max:200'],
            'condition_values.*' => ['string', 'max:2000'],
            /** @var string[]|null */
            'disqualifying_options' => ['nullable', 'array', 'max:200'],
            'disqualifying_options.*' => ['string', 'max:2000'],
            'disqualification_message' => ['nullable', 'string', 'max:500'],
            'option_renames' => ['nullable', 'array', 'max:200'],
            'option_renames.*.from' => ['required', 'string', 'max:2000'],
            'option_renames.*.to' => ['required', 'string', 'max:2000'],
            'belongs_to' => ['required', Rule::in([QuestionBelongsTo::PRODUCT->name, QuestionBelongsTo::ORDER->name])],
            /** @var string[]|null */
            'options' => 'max:2000|required_if:type,CHECKBOX,RADIO',
            'required' => 'required|boolean',
            'is_hidden' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => __('Please select at least one product.'),
        ];
    }
}
