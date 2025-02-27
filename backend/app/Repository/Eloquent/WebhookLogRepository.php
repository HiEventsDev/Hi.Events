<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\WebhookLogDomainObject;
use HiEvents\Models\WebhookLog;
use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;

class WebhookLogRepository extends BaseRepository implements WebhookLogRepositoryInterface
{
    protected function getModel(): string
    {
        return WebhookLog::class;
    }

    public function getDomainObject(): string
    {
        return WebhookLogDomainObject::class;
    }

    /**
     * @todo This should be a scheduled task
     */
    public function deleteOldLogs(int $webhookId, int $numberToKeep = 20): void
    {
        $query = $this->model->where('webhook_id', $webhookId);

        $totalLogs = $query->count();

        if ($totalLogs > $numberToKeep) {
            $query->orderBy('created_at', 'desc')
                ->skip($numberToKeep)
                ->take($totalLogs - $numberToKeep)
                ->forceDelete();
        }
    }
}
