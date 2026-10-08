<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Resources;

use HiEvents\Enterprise\Licensing\DTO\LicenceStateDTO;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenceStateDTO
 */
class LicenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var 'ACTIVE'|'GRACE'|'LAPSED'|'NONE'|'DEV' */
            'status' => $this->status->value,
            /** @var string[] */
            'features' => array_map(static fn (LicensedFeature $feature) => $feature->value, $this->features),
            'lid' => $this->licence?->lid,
            'customer' => $this->licence?->customer,
            'plan' => $this->licence?->plan->value,
            'issued_at' => $this->licence?->issued_at->toDateString(),
            'expires_at' => $this->licence?->expires_at->toDateString(),
            'grace_ends_at' => $this->grace_ends_at?->toDateString(),
            'invalid_reason' => $this->invalid_reason,
        ];
    }
}
