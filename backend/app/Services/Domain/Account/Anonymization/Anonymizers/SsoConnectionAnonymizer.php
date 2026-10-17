<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class SsoConnectionAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $connectionIds = $this->databaseManager->table('account_sso_connections')
            ->where('account_id', $context->accountId)
            ->pluck('id')
            ->all();

        $results = [];

        if ($connectionIds !== []) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('user_identities')->whereIn('account_sso_connection_id', $connectionIds),
                entity: 'user_identities',
            );
        }

        $results[] = $this->executor->delete(
            query: $this->databaseManager->table('account_sso_domains')->where('account_id', $context->accountId),
            entity: 'account_sso_domains',
        );

        $results[] = $this->executor->delete(
            query: $this->databaseManager->table('account_sso_connections')->where('account_id', $context->accountId),
            entity: 'account_sso_connections',
        );

        return $results;
    }
}
