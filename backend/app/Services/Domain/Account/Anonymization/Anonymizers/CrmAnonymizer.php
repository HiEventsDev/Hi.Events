<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class CrmAnonymizer implements AccountAnonymizerInterface
{
    public const ACCOUNT_TABLES = [
        'crm_signup_forms',
        'crm_bulk_operations',
        'crm_campaign_templates',
        'crm_imports',
        'crm_campaigns',
        'crm_automations',
        'crm_segments',
        'crm_contacts',
        'crm_tags',
        'crm_organizer_states',
        'crm_sms_connections',
    ];

    public const ORGANIZER_TABLES = [
        'crm_pending_subscriptions',
        'crm_deleted_contacts',
        'crm_deleted_contact_salts',
    ];

    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $results = [];

        foreach ($context->organizerIds === [] ? [] : self::ORGANIZER_TABLES as $table) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table($table)->whereIn('organizer_id', $context->organizerIds),
                entity: $table,
            );
        }

        foreach (self::ACCOUNT_TABLES as $table) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table($table)->where('account_id', $context->accountId),
                entity: $table,
            );
        }

        return $results;
    }
}
