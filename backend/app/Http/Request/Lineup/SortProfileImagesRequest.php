<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Validator;

class SortProfileImagesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sorted_ids' => ['required', 'array', 'max:50'],
            'sorted_ids.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('sorted_ids');

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('sorted_ids', __('Each photo may only appear once'));
            }
        });
    }
}
