<?php

namespace HiEvents\Resources\User;

use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

class AdminUserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $accounts = $this->resource->accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'role' => $account->pivot->role,
                'is_account_owner' => $account->pivot->is_account_owner,
                'last_login_at' => $account->pivot->last_login_at,
                'status' => $account->pivot->status,
            ];
        });

        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->first_name . ' ' . $this->resource->last_name,
            'email' => $this->resource->email,
            'timezone' => $this->resource->timezone,
            'locale' => $this->resource->locale,
            'is_email_verified' => $this->resource->email_verified_at !== null,
            'created_at' => $this->resource->created_at,
            'accounts' => $accounts,
        ];
    }
}
