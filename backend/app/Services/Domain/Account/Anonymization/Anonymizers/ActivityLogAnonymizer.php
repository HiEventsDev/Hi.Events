<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class ActivityLogAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $results = [];

        if ($context->eventIds !== []) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('order_audit_logs')->whereIn('event_id', $context->eventIds),
                entity: 'order_audit_logs',
            );

            $webhookIds = $this->databaseManager->table('webhooks')
                ->where(function ($query) use ($context) {
                    $query->where('account_id', $context->accountId)
                        ->orWhereIn('event_id', $context->eventIds);
                })
                ->pluck('id')
                ->all();

            if ($webhookIds !== []) {
                $results[] = $this->executor->delete(
                    query: $this->databaseManager->table('webhook_logs')->whereIn('webhook_id', $webhookIds),
                    entity: 'webhook_logs',
                );
            }
        }

        $results[] = $this->executor->delete(
            query: $this->databaseManager->table('webhooks')->where('account_id', $context->accountId),
            entity: 'webhooks',
        );

        $results[] = $this->executor->delete(
            query: $this->databaseManager->table('email_templates')->where('account_id', $context->accountId),
            entity: 'email_templates',
        );

        if ($context->soleUserIds !== []) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('event_logs')->whereIn('user_id', $context->soleUserIds),
                entity: 'event_logs',
            );
        }

        return $results;
    }
}
