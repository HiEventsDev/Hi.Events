<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class UpsertSeatMapRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'integer'],
            'layout' => ['required', 'array'],
            'layout.schema' => ['required', 'integer'],
            'layout.bands' => ['required', 'array'],
            'layout.areas' => ['required', 'array'],
        ];
    }
}
