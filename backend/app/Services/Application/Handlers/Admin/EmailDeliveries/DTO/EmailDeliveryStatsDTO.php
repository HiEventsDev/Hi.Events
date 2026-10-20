<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\EmailDeliveries\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Repository\DTO\EmailDelivery\EmailDeliveryCategoryStatsDTO;
use Illuminate\Support\Collection;

class EmailDeliveryStatsDTO extends BaseDataObject
{
    /**
     * @param  Collection<int, EmailDeliveryCategoryStatsDTO>  $categories
     */
    public function __construct(
        public bool $deliveryWebhookConfigured,
        public Collection $categories,
    ) {}
}
