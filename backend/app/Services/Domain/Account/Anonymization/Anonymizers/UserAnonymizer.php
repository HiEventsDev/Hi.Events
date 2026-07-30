<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class UserAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $results = [];

        if ($context->soleUserEmails !== []) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('password_resets')->whereIn('email', $context->soleUserEmails),
                entity: 'password_resets',
            );

            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('password_reset_tokens')->whereIn('email', $context->soleUserEmails),
                entity: 'password_reset_tokens',
            );
        }

        if ($context->soleUserIds !== []) {
            $results[] = $this->executor->delete(
                query: $this->databaseManager->table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $context->soleUserIds),
                entity: 'personal_access_tokens',
            );

            $results[] = $this->executor->scrub(
                query: $this->databaseManager->table('users')->whereIn('id', $context->soleUserIds),
                entity: 'users',
                columnStrategies: [
                    'first_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'last_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL_UNIQUE,
                    'pending_email' => AnonymizationStrategy::NULLIFY,
                    'password' => AnonymizationStrategy::RANDOM_TOKEN,
                    'remember_token' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            );

            $results[] = $this->executor->softDelete(
                query: $this->databaseManager->table('users')->whereIn('id', $context->soleUserIds),
                entity: 'users',
            );
        }

        $results[] = $this->executor->softDelete(
            query: $this->databaseManager->table('account_users')->where('account_id', $context->accountId),
            entity: 'account_users',
        );

        return $results;
    }
}
