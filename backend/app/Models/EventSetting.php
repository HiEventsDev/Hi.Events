<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class EventSetting extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'location_details' => 'array',
            'payment_providers' => 'array',
            'ticket_design_settings' => 'array',
            'homepage_theme_settings' => 'array',
            'social_media_handles' => 'array',
            'stripe_payment_method_order' => 'array',
        ];
    }
}
