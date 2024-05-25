<?php

namespace HiEvents\Http\Request\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use HiEvents\DomainObjects\Enums\EventImageType;

class CreateEventImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'max:8192', //8mb
                'dimensions:min_width=600,min_height=50,max_width=3000,max_height=2000',
                'mimes:jpeg,png,jpg,webp',
            ],
            'type' => Rule::in(EventImageType::valuesArray()),
        ];
    }
}
