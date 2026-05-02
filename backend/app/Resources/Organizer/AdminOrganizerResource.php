<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

class AdminOrganizerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource->account;

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'website' => $this->resource->website,
            'currency' => $this->resource->currency,
            'timezone' => $this->resource->timezone,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
            ] : null,
        ];
    }
}
