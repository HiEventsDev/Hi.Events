<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class UpdateEventSeatMapLayoutRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer'],
            'confirm_relabel' => ['nullable', 'boolean'],
            'layout' => ['required', 'array'],
            'layout.schema' => ['required', 'integer'],
            'layout.bands' => ['required', 'array'],
            'layout.areas' => ['required', 'array'],
        ];
    }
}
