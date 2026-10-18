<?php

namespace HiEvents\Enterprise\Sso\Resources;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin AccountSsoDomainDomainObject
 */
class AccountSsoDomainResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'domain' => $this->getDomain(),
            /** @var 'PENDING'|'VERIFIED'|'FAILING'|'SUSPENDED' */
            'status' => $this->getDomainStatus()->value,
            'txt_record_name' => $this->getTxtRecordName(),
            'txt_record_value' => $this->getTxtRecordValue(),
            'verified_at' => $this->getVerifiedAt(),
            'last_checked_at' => $this->getLastCheckedAt(),
            'failing_since' => $this->getFailingSince(),
        ];
    }
}
