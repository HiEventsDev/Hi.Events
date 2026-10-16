<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

class CreateLineupEntryRequest extends UpdateLineupEntryRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'profile_id' => ['required', 'integer'],
        ];
    }
}
