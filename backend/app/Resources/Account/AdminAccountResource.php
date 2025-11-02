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
            'users' => $this->resource->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                ];
            }),
        ];
    }
}
