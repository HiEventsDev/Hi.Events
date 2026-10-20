<?php

namespace HiEvents\Resources\Account;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountDeletionRequestDomainObject
 */
class AccountDeletionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'status' => $this->getStatus(),
            'initiated_by' => $this->getInitiatedBy(),
            'expected_outcome' => $this->getExpectedOutcome(),
            'scheduled_deletion_at' => $this->getScheduledDeletionAt(),
            'cancelled_at' => $this->getCancelledAt(),
            'requested_at' => $this->getCreatedAt(),
        ];
    }
}
