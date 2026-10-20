<?php

namespace Tests\Feature\Services\Domain\Auth\ApiKey;

use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\Jobs\ApiKey\SendApiKeyExpiryRemindersJob;
use HiEvents\Mail\User\ApiKeyExpiringMail;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyExpiryReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class ApiKeyExpiryReminderPendingDeletionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    public function test_skips_accounts_pending_deletion_and_leaves_the_key_unmarked(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $keyId = $this->dueKey($owner, $accountId);
        $this->requestDeletion($owner, $accountId, AccountDeletionRequestStatus::REQUESTED);

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertNothingQueued();
        $this->assertNull(DB::table('personal_access_tokens')->where('id', $keyId)->value('expiry_reminder_sent_at'));
    }

    public function test_still_reminds_after_a_cancelled_deletion_and_on_other_accounts(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $keyId = $this->dueKey($owner, $accountId);
        $this->requestDeletion($owner, $accountId, AccountDeletionRequestStatus::CANCELLED);

        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $this->requestDeletion($otherOwner, $otherAccountId, AccountDeletionRequestStatus::REQUESTED);

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertQueued(ApiKeyExpiringMail::class, fn (ApiKeyExpiringMail $mail) => $mail->hasTo($owner->email));
        $this->assertNotNull(DB::table('personal_access_tokens')->where('id', $keyId)->value('expiry_reminder_sent_at'));
    }

    private function dueKey(User $owner, int $accountId): int
    {
        $this->createApiKey($owner, $accountId);
        $keyId = DB::table('personal_access_tokens')->max('id');

        DB::table('personal_access_tokens')->where('id', $keyId)->update([
            'created_at' => now()->subDays(85),
            'expires_at' => now()->addDays(5),
        ]);

        return $keyId;
    }

    private function requestDeletion(User $owner, int $accountId, AccountDeletionRequestStatus $status): void
    {
        DB::table('account_deletion_requests')->insert([
            'account_id' => $accountId,
            'requested_by_user_id' => $owner->id,
            'initiated_by' => 'USER',
            'status' => $status->name,
            'expected_outcome' => 'HARD_DELETE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
