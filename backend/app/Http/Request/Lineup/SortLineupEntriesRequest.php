<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Validator;

class SortLineupEntriesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sorted_ids' => ['required', 'array', 'max:1000'],
            'sorted_ids.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('sorted_ids', []);

            if (is_array($ids) && count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('sorted_ids', __('Each lineup entry may only appear once'));
            }
        });
    }
}
