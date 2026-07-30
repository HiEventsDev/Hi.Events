<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class EventContentAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        if ($context->eventIds === []) {
            return [];
        }

        $questionIds = $this->databaseManager->table('questions')
            ->whereIn('event_id', $context->eventIds)
            ->pluck('id')
            ->all();

        return array_values(array_filter([
            $questionIds === [] ? null : $this->executor->delete(
                query: $this->databaseManager->table('question_answers')->whereIn('question_id', $questionIds),
                entity: 'question_answers',
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('outgoing_messages')->whereIn('event_id', $context->eventIds),
                entity: 'outgoing_messages',
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('messages')->whereIn('event_id', $context->eventIds),
                entity: 'messages',
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('waitlist_entries')->whereIn('event_id', $context->eventIds),
                entity: 'waitlist_entries',
            ),
            $this->executor->scrub(
                query: $this->databaseManager->table('event_settings')->whereIn('event_id', $context->eventIds),
                entity: 'event_settings',
                columnStrategies: [
                    'support_email' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->softDelete(
                query: $this->databaseManager->table('events')->where('account_id', $context->accountId),
                entity: 'events',
            ),
        ]));
    }
}
