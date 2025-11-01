<?php

namespace HiEvents\Resources\Account;

use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

class AdminAccountResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'timezone' => $this->resource->timezone,
            'currency_code' => $this->resource->currency_code,
            'created_at' => $this->resource->created_at,
            'events_count' => $this->resource->events_count ?? 0,
            'users_count' => $this->resource->users_count ?? 0,
        ];
    }
}
