<?php

namespace Tests\Feature\Services\Domain\Auth\ApiKey;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
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

class ApiKeyExpiryReminderServiceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
    }

    public function test_reminds_the_owner_once_when_a_key_is_about_to_expire(): void
    {
        $keyId = $this->keyCreatedDaysAgo($this->owner, createdDaysAgo: 85, expiresInDays: 5);

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertQueued(ApiKeyExpiringMail::class, fn (ApiKeyExpiringMail $mail) => $mail->hasTo($this->owner->email));
        $this->assertNotNull(DB::table('personal_access_tokens')->where('id', $keyId)->value('expiry_reminder_sent_at'));

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertNothingQueued();
    }

    public function test_skips_keys_that_are_not_due_short_lived_or_never_expire(): void
    {
        $this->keyCreatedDaysAgo($this->owner, createdDaysAgo: 30, expiresInDays: 60);
        $this->keyCreatedDaysAgo($this->owner, createdDaysAgo: 2, expiresInDays: 5);
        $this->keyCreatedDaysAgo($this->owner, createdDaysAgo: 100, expiresInDays: -1);
        $this->keyCreatedDaysAgo($this->owner, createdDaysAgo: 100, expiresInDays: null);

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertNothingQueued();
    }

    public function test_does_not_email_members_who_lost_access(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $keyId = $this->keyCreatedDaysAgo($member, createdDaysAgo: 85, expiresInDays: 5);
        DB::table('account_users')->where(['user_id' => $member->id])->update(['status' => UserStatus::INACTIVE->name]);

        Mail::fake();
        (new SendApiKeyExpiryRemindersJob)->handle(app(ApiKeyExpiryReminderService::class));

        Mail::assertNothingQueued();
        $this->assertNotNull(DB::table('personal_access_tokens')->where('id', $keyId)->value('expiry_reminder_sent_at'));
    }

    private function keyCreatedDaysAgo(User $user, int $createdDaysAgo, ?int $expiresInDays): int
    {
        $this->createApiKey($user, $this->accountId);
        $keyId = DB::table('personal_access_tokens')->max('id');

        DB::table('personal_access_tokens')->where('id', $keyId)->update([
            'created_at' => now()->subDays($createdDaysAgo),
            'expires_at' => $expiresInDays === null ? null : now()->addDays($expiresInDays),
        ]);

        return $keyId;
    }
}
