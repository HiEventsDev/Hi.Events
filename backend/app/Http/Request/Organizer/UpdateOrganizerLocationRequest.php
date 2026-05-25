<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Organizer;

use HiEvents\Http\Request\BaseRequest;

class UpdateOrganizerLocationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer'],
        ];
    }
}
