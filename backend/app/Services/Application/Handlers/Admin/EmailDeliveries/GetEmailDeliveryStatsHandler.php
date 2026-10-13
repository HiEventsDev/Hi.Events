<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\EmailDeliveries;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\EmailDeliveries\DTO\EmailDeliveryStatsDTO;
use HiEvents\Services\Infrastructure\Email\Delivery\EmailDeliveryWebhookReceiverResolver;

class GetEmailDeliveryStatsHandler
{
    public function __construct(
        private readonly EmailDeliveryRepositoryInterface $emailDeliveryRepository,
        private readonly EmailDeliveryWebhookReceiverResolver $receiverResolver,
    ) {}

    public function handle(AdminDashboardPeriod $period): EmailDeliveryStatsDTO
    {
        return new EmailDeliveryStatsDTO(
            deliveryWebhookConfigured: $this->receiverResolver->anyConfigured(),
            categories: $this->emailDeliveryRepository->getCategoryStats($period->since(CarbonImmutable::now())),
        );
    }
}
