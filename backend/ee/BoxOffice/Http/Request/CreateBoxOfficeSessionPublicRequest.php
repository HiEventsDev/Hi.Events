<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class CreateBoxOfficeSessionPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'operator_name' => ['required', 'string', 'max:60'],
            'pin' => ['nullable', 'string', 'digits_between:4,8'],
            'event_occurrence_id' => ['nullable', 'integer'],
            'stripe_terminal_reader_id' => ['nullable', 'integer'],
        ];
    }
}
