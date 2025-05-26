<?php

namespace HiEvents\Http\Request\Image;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => RulesHelper::IMAGE_RULES,
            'image_type' => [
                Rule::in(ImageType::valuesArray()),
                'required_with:entity_id'
            ],
            'entity_id' => ['integer', 'required_with:image_type'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.dimensions' => __('The image must be at least 600 pixels wide and 50 pixels tall, and no more than 4000 pixels wide and 4000 pixels tall.'),
            'entity_id.required_with' => __('The entity ID is required when type is provided.'),
            'image_type.required_with' => __('The type is required when entity ID is provided.'),
        ];
    }
}
