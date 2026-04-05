<?php

declare(strict_types=1);

namespace HiEvents\Models;

class MfaAuditLog extends BaseModel
{
    protected $table = 'mfa_audit_logs';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
