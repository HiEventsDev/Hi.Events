<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class AccountAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        return [
            $this->executor->scrub(
                query: $this->databaseManager->table('account_vat_settings')->where('account_id', $context->accountId),
                entity: 'account_vat_settings',
                columnStrategies: [
                    'vat_number' => AnonymizationStrategy::NULLIFY,
                    'business_name' => AnonymizationStrategy::NULLIFY,
                    'business_address' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('account_attributions')->where('account_id', $context->accountId),
                entity: 'account_attributions',
            ),
            $this->executor->delete(
                query: $this->databaseManager->table('account_stripe_platforms')->where('account_id', $context->accountId),
                entity: 'account_stripe_platforms',
            ),
            $this->executor->scrub(
                query: $this->databaseManager->table('accounts')->where('id', $context->accountId),
                entity: 'accounts',
                columnStrategies: [
                    'name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                    'stripe_account_id' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
            $this->executor->softDelete(
                query: $this->databaseManager->table('accounts')->where('id', $context->accountId),
                entity: 'accounts',
            ),
        ];
    }
}
