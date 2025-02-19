<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Models\Webhook;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;

class WebhookRepository extends BaseRepository implements WebhookRepositoryInterface
{
    protected function getModel(): string
    {
        return Webhook::class;
    }

    public function getDomainObject(): string
    {
        return WebhookDomainObject::class;
    }
}
