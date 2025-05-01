<?php

namespace HiEvents\Http\Request\Image;

use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Foundation\Http\FormRequest;

class CreateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => RulesHelper::IMAGE_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'image.dimensions' => __('The image must be at least 600 pixels wide and 50 pixels tall, and no more than 4000 pixels wide and 4000 pixels tall.'),
        ];
    }
}
