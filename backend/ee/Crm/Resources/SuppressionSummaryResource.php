<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\SuppressionSummaryDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SuppressionSummaryDTO
 */
class SuppressionSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'unsubscribed' => $this->unsubscribed,
            'bounced' => $this->bounced,
            'complained' => $this->complained,
            'deleted' => $this->deleted,
        ];
    }
}
