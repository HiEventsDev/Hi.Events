<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\Http\Request\BaseRequest;

class UpsertEventSpaceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'event_location_id' => ['nullable', 'integer'],
        ];
    }
}
