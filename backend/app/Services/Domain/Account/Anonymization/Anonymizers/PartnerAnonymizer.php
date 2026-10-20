<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class PartnerAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $results = [
            $this->executor->scrub(
                query: $this->databaseManager->table('affiliates')->where('account_id', $context->accountId),
                entity: 'affiliates',
                columnStrategies: [
                    'name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                ],
                context: $context,
            ),
        ];

        if ($context->stripeAccountIds !== []) {
            $results[] = $this->executor->scrub(
                query: $this->databaseManager->table('stripe_customers')->whereIn('stripe_account_id', $context->stripeAccountIds),
                entity: 'stripe_customers',
                columnStrategies: [
                    'name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                ],
                context: $context,
            );
        }

        return $results;
    }
}
