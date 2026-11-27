<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionDetailDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\BounceReactivationPolicy;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SuppressionDetailDTO
 */
class SuppressionDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var 'UNSUBSCRIBED'|'BOUNCED'|'COMPLAINED' */
            'reason' => $this->reason,
            'since' => $this->since,
            'source_type' => $this->source_type,
            'email' => $this->email,
            'campaign_name' => $this->campaign_name,
            'import_file_name' => $this->import_file_name,
            'user_name' => $this->user_name,
            'provider_rejected' => $this->provider_rejected,
            'reactivation_available_at' => BounceReactivationPolicy::availableAt($this->provider_rejected_at)?->toIso8601String(),
        ];
    }
}
