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
            'social_media_handles' => 'array',
            'show_share_buttons' => 'boolean',
            'hide_getting_started_page' => 'boolean',
            'is_online_event' => 'boolean',
            'notify_organizer_of_new_orders' => 'boolean',
            'require_attendee_details' => 'boolean',
            'require_billing_address' => 'boolean',
            'allow_orders_awaiting_offline_payment_to_check_in' => 'boolean',
            'enable_invoicing' => 'boolean',
            'allow_search_engine_indexing' => 'boolean',
        ];
    }
}
