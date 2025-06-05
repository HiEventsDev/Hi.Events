<?php

namespace HiEvents\Http\Request\Image;

use HiEvents\DomainObjects\Enums\ImageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateImageRequest extends FormRequest
{
    public function rules(): array
    {
        $imageType = $this->input('image_type')
            ? ImageType::fromName($this->input('image_type'))
            : ImageType::GENERIC;

        [$minWidth, $minHeight] = ImageType::getMinimumDimensionsMap($imageType);

        return [
            'image' => [
                'required',
                'image',
                'max:8192', //8mb
                'dimensions:min_width=' . $minWidth . ',min_height=' . $minHeight . ',max_width=4000,max_height=4000',
                'mimes:jpeg,png,jpg,webp',
            ],
            'image_type' => [
                Rule::in(ImageType::valuesArray()),
                'required_with:entity_id'
            ],
            'entity_id' => ['integer', 'required_with:image_type'],
        ];
    }

    public function messages(): array
    {
        $imageType = $this->input('image_type')
            ? ImageType::fromName($this->input('image_type'))
            : ImageType::GENERIC;

        [$minWidth, $minHeight] = ImageType::getMinimumDimensionsMap($imageType);

        return [
            'image.dimensions' => __('The image must be at least :minWidth x :minHeight pixels.', [
                'minWidth' => $minWidth,
                'minHeight' => $minHeight,
            ]),
            'entity_id.required_with' => __('The entity ID is required when type is provided.'),
            'image_type.required_with' => __('The type is required when entity ID is provided.'),
        ];
    }
}
