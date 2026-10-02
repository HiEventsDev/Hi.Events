<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class MoveAttendeeSeatRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'seat_uid' => ['required', 'string', 'max:24'],
        ];
    }
}
