<?php

namespace HiEvents\Resources\Admin;

use HiEvents\Models\AccountDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountDeletionRequest
 */
class AdminAccountDeletionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'initiated_by' => $this->initiated_by,
            'reason' => $this->reason,
            'expected_outcome' => $this->expected_outcome,
            'outcome' => $this->outcome,
            'scheduled_deletion_at' => $this->scheduled_deletion_at?->toIso8601String(),
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'requested_at' => $this->created_at?->toIso8601String(),
            'deletion_manifest' => $this->deletion_manifest,
            'account' => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
                'email' => $this->account->email,
            ] : null,
            'requested_by_user' => $this->requestedByUser ? [
                'id' => $this->requestedByUser->id,
                'full_name' => trim($this->requestedByUser->first_name.' '.$this->requestedByUser->last_name),
                'email' => $this->requestedByUser->email,
            ] : null,
            'cancelled_by_user' => $this->cancelledByUser ? [
                'id' => $this->cancelledByUser->id,
                'full_name' => trim($this->cancelledByUser->first_name.' '.$this->cancelledByUser->last_name),
            ] : null,
        ];
    }
}
