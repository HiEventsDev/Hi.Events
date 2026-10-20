<?php

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\EmailDeliveryDomainObject;
use HiEvents\Repository\DTO\EmailDelivery\EmailDeliveryCategoryStatsDTO;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryEventDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<EmailDeliveryDomainObject>
 */
interface EmailDeliveryRepositoryInterface extends RepositoryInterface
{
    public function recordEvent(EmailDeliveryEventDTO $event): void;

    /**
     * @return Collection<int, EmailDeliveryCategoryStatsDTO>
     */
    public function getCategoryStats(CarbonInterface $since): Collection;
}
