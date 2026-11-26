<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\SuppressionLookupResultDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SuppressionLookupResultDTO
 */
class SuppressionLookupResultResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
            /** @var 'contact'|'deleted'|'not_found'|'invalid' */
            'result' => $this->result,
            'contact' => $this->contact === null ? null : [
                'id' => $this->contact->getId(),
                'full_name' => trim(($this->contact->getFirstName() ?? '').' '.($this->contact->getLastName() ?? '')),
                'email_marketing_status' => $this->contact->getEmailMarketingStatus(),
                'email_marketing_status_changed_at' => $this->contact->getEmailMarketingStatusChangedAt(),
            ],
            'suppression' => $this->suppression === null ? null : new SuppressionDetailResource($this->suppression),
            /** @var 'UNSUBSCRIBED'|'BOUNCED'|'COMPLAINED'|null */
            'deleted_suppression' => $this->deleted_suppression,
        ];
    }
}
