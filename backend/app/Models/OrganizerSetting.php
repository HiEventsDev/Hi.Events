<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizerSetting extends BaseModel
{
    use SoftDeletes;

    public function getCastMap(): array
    {
        return [
            'social_media_handles' => 'array',
            'homepage_theme_settings' => 'array',
            'location_details' => 'array',
        ];
    }
}
