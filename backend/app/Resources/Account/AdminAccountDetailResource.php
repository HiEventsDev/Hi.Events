<?php

namespace HiEvents\Resources\Account;

use HiEvents\DomainObjects\AccountMessagingTierDomainObject;
use HiEvents\Http\Resources\Admin\AccountMessagingTierResource;
use HiEvents\Models\Account;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin Account
 */
class AdminAccountDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'timezone' => $this->resource->timezone,
            'currency_code' => $this->resource->currency_code,
            'is_manually_verified' => (bool) $this->resource->is_manually_verified,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'events_count' => $this->resource->events_count ?? 0,
            'users_count' => $this->resource->users_count ?? 0,
            'organizers' => $this->resource->organizers
                ? $this->resource->organizers->map(function ($organizer) {
                    $configuration = $organizer->organizer_configuration;
                    $vatSetting = $organizer->organizer_vat_setting;

                    return [
                        'id' => $organizer->id,
                        'name' => $organizer->name,
                        'configuration' => $configuration ? [
                            'id' => $configuration->id,
                            'name' => $configuration->name,
                            'is_system_default' => $configuration->is_system_default,
                            'default_for_currency' => $configuration->default_for_currency,
                            'application_fees' => $configuration->application_fees ?? [
                                'percentage' => 0,
                                'fixed' => 0,
                            ],
                            'bypass_application_fees' => (bool) ($configuration->bypass_application_fees ?? false),
                        ] : null,
                        'vat_setting' => $vatSetting ? [
                            'id' => $vatSetting->id,
                            'vat_registered' => (bool) $vatSetting->vat_registered,
                            'vat_number' => $vatSetting->vat_number,
                            'vat_validated' => (bool) $vatSetting->vat_validated,
                            'vat_validation_status' => $vatSetting->vat_validation_status,
                            'vat_validation_date' => $vatSetting->vat_validation_date,
                            'business_name' => $vatSetting->business_name,
                            'business_address' => $vatSetting->business_address,
                            'vat_country_code' => $vatSetting->vat_country_code,
                        ] : null,
                    ];
                })->values()
                : [],
            'users' => $this->resource->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                ];
            }),
            'messaging_tier' => $this->resource->messagingTier
                ? new AccountMessagingTierResource(
                    AccountMessagingTierDomainObject::hydrateFromModel($this->resource->messagingTier)
                )
                : null,
        ];
    }
}
