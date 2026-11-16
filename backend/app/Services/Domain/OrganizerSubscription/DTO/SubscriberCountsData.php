<?php

namespace HiEvents\Services\Domain\OrganizerSubscription\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SubscriberCountsData extends BaseDataObject
{
    public function __construct(
        public readonly int $confirmed,
        public readonly int $pending,
        public readonly int $unsubscribed,
    ) {}
}
