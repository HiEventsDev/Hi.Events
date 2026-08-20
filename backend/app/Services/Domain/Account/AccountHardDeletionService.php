<?php

namespace HiEvents\Services\Domain\Account;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Account\Anonymization\AccountDataResolver;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Throwable;

class AccountHardDeletionService
{
    public function __construct(
        private readonly AccountDataResolver $dataResolver,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function deleteAccount(int $accountId): array
    {
        $context = $this->dataResolver->resolve($accountId);

        $this->logger->info('Hard deleting account', [
            'account_id' => $accountId,
            'stripe_account_ids' => $context->stripeAccountIds,
        ]);

        $manifest = $this->databaseManager->transaction(fn () => $this->deleteAccountData($context));

        $this->deleteImageFiles($context->imageFiles);

        $this->logger->info('Account hard deleted', [
            'account_id' => $accountId,
            'manifest' => $manifest,
        ]);

        return $manifest;
    }

    private function deleteAccountData(AnonymizationContext $context): array
    {
        $manifest = [];
        $connection = $this->databaseManager->connection();

        if ($context->eventIds !== []) {
            $questionIds = $connection->table('questions')
                ->whereIn('event_id', $context->eventIds)
                ->pluck('id')
                ->all();

            if ($questionIds !== []) {
                $manifest['question_answers'] = $connection->table('question_answers')
                    ->whereIn('question_id', $questionIds)
                    ->delete();
            }
        }

        if ($context->orderIds !== []) {
            $manifest['attendee_check_ins'] = $connection->table('attendee_check_ins')
                ->whereIn('order_id', $context->orderIds)
                ->delete();

            $manifest['stripe_payments'] = $connection->table('stripe_payments')
                ->whereIn('order_id', $context->orderIds)
                ->delete();

            $manifest['orders'] = $connection->table('orders')
                ->whereIn('id', $context->orderIds)
                ->delete();
        }

        if ($context->eventIds !== []) {
            $manifest['order_audit_logs'] = $connection->table('order_audit_logs')
                ->whereIn('event_id', $context->eventIds)
                ->delete();

            $manifest['outgoing_messages'] = $connection->table('outgoing_messages')
                ->whereIn('event_id', $context->eventIds)
                ->delete();

            $manifest['messages'] = $connection->table('messages')
                ->whereIn('event_id', $context->eventIds)
                ->delete();

            $manifest['promo_codes'] = $connection->table('promo_codes')
                ->whereIn('event_id', $context->eventIds)
                ->delete();

            $manifest['questions'] = $connection->table('questions')
                ->whereIn('event_id', $context->eventIds)
                ->delete();

            foreach ([
                'event_statistics',
                'event_daily_statistics',
                'event_occurrence_statistics',
                'event_occurrence_daily_statistics',
            ] as $statisticsTable) {
                $manifest[$statisticsTable] = $connection->table($statisticsTable)
                    ->whereIn('event_id', $context->eventIds)
                    ->delete();
            }

            $manifest['events'] = $connection->table('events')
                ->whereIn('id', $context->eventIds)
                ->delete();
        }

        if ($context->organizerIds !== []) {
            $manifest['organizers'] = $connection->table('organizers')
                ->whereIn('id', $context->organizerIds)
                ->delete();
        }

        $manifest['roles'] = $connection->table('roles')
            ->where('account_id', $context->accountId)
            ->delete();

        $manifest['taxes_and_fees'] = $connection->table('taxes_and_fees')
            ->where('account_id', $context->accountId)
            ->delete();

        if ($context->stripeAccountIds !== []) {
            $manifest['stripe_customers'] = $connection->table('stripe_customers')
                ->whereIn('stripe_account_id', $context->stripeAccountIds)
                ->delete();
        }

        if ($context->soleUserEmails !== []) {
            $manifest['password_resets'] = $connection->table('password_resets')
                ->whereIn('email', $context->soleUserEmails)
                ->delete();

            $manifest['password_reset_tokens'] = $connection->table('password_reset_tokens')
                ->whereIn('email', $context->soleUserEmails)
                ->delete();
        }

        if ($context->soleUserIds !== []) {
            $manifest['personal_access_tokens'] = $connection->table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $context->soleUserIds)
                ->delete();

            $manifest['event_logs'] = $connection->table('event_logs')
                ->whereIn('user_id', $context->soleUserIds)
                ->delete();
        }

        $manifest['accounts'] = $connection->table('accounts')
            ->where('id', $context->accountId)
            ->delete();

        if ($context->soleUserIds !== []) {
            $manifest['users'] = $connection->table('users')
                ->whereIn('id', $context->soleUserIds)
                ->delete();
        }

        return $manifest;
    }

    private function deleteImageFiles(array $imageFiles): void
    {
        foreach ($imageFiles as $imageFile) {
            try {
                Storage::disk($imageFile['disk'])->delete($imageFile['path']);
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to delete image file during account deletion', [
                    'disk' => $imageFile['disk'],
                    'path' => $imageFile['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
