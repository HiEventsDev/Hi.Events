<?php

declare(strict_types=1);

namespace HiEvents\Models;

class WebAuthnCredential extends BaseModel
{
    protected $table = 'webauthn_credentials';

    protected $guarded = [];

    protected $casts = [
        'transports' => 'array',
        'is_discoverable' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
