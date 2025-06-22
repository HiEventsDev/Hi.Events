<?php

namespace HiEvents\Http\Request\Event;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEventImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => RulesHelper::IMAGE_RULES,
            'type' => Rule::in(ImageType::eventImageTypes()),
        ];
    }

    public function messages(): array
    {
        return [
            'image.dimensions' => __('The image must be at least 600 pixels wide and 50 pixels tall, and no more than 4000 pixels wide and 4000 pixels tall.'),
        ];
    }
}
