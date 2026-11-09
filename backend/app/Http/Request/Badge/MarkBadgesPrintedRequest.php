<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Badge;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use Illuminate\Validation\Validator;

class MarkBadgesPrintedRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_ids' => ['required', 'array', 'min:1', 'max:'.BadgeAttendeeQueryDTO::MAX_PER_PAGE],
            'attendee_ids.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('attendee_ids');
            if (is_array($ids) && count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('attendee_ids', __('Each attendee can only be listed once.'));
            }
        });
    }
}
