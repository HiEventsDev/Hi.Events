<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'deletion_manifest' => 'array',
            'scheduled_deletion_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'requested_by_user_id',
            'initiated_by',
            'reason',
            'status',
            'expected_outcome',
            'outcome',
            'scheduled_deletion_at',
            'reminder_sent_at',
            'cancelled_at',
            'cancelled_by_user_id',
            'completed_at',
            'deletion_manifest',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id')->withTrashed();
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id')->withTrashed();
    }
}
