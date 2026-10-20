<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class UpdateBoxOfficeSessionPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event_occurrence_id' => ['nullable', 'integer'],
            'stripe_terminal_reader_id' => ['nullable', 'integer'],
        ];
    }
}
