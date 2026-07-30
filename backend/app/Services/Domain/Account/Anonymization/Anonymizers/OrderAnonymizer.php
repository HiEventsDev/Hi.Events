<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class OrderAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        if ($context->orderIds === []) {
            return [];
        }

        return [
            $this->executor->scrub(
                query: $this->databaseManager->table('orders')->whereIn('id', $context->orderIds),
                entity: 'orders',
                columnStrategies: [
                    'first_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'last_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                    'address' => AnonymizationStrategy::NULLIFY,
                    'session_id' => AnonymizationStrategy::NULLIFY,
                    'notes' => AnonymizationStrategy::NULLIFY,
                    'point_in_time_data' => AnonymizationStrategy::NULLIFY,
                    'public_id' => AnonymizationStrategy::RANDOM_TOKEN,
                ],
                context: $context,
            ),
            $this->executor->scrub(
                query: $this->databaseManager->table('attendees')->whereIn('order_id', $context->orderIds),
                entity: 'attendees',
                columnStrategies: [
                    'first_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'last_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                    'notes' => AnonymizationStrategy::NULLIFY,
                    'public_id' => AnonymizationStrategy::RANDOM_TOKEN,
                ],
                context: $context,
            ),
        ];
    }
}
