<?php

namespace HiEvents\Resources\Admin;

use HiEvents\Repository\DTO\EmailDelivery\EmailDeliveryCategoryStatsDTO;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Admin\EmailDeliveries\DTO\EmailDeliveryStatsDTO;
use Illuminate\Http\Request;

/**
 * @mixin EmailDeliveryStatsDTO
 */
class AdminEmailDeliveryStatsResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'delivery_webhook_configured' => $this->deliveryWebhookConfigured,
            'categories' => $this->categories->map(static fn (EmailDeliveryCategoryStatsDTO $stats) => [
                'category' => $stats->category,
                'sent' => $stats->sent,
                'delivered' => $stats->delivered,
                'permanent_bounces' => $stats->permanentBounces,
                'transient_bounces' => $stats->transientBounces,
                'complaints' => $stats->complaints,
                'opened' => $stats->opened,
                'clicked' => $stats->clicked,
            ])->values()->all(),
        ];
    }
}
