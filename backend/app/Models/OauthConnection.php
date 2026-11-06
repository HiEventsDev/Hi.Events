<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthConnection extends BaseModel
{
    public function oauth_client(): BelongsTo
    {
        return $this->belongsTo(OauthClient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
