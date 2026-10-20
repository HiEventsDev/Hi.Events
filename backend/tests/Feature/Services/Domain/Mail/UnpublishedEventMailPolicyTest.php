<?php

namespace Tests\Feature\Services\Domain\Mail;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\Account;
use HiEvents\Models\AccountUser;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class UnpublishedEventMailPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private ?int $previousCurrentAccountId = null;

    private bool $hadCurrentAccountId = false;

    protected function setUp(): void
    {
        parent::setUp();

        $property = new ReflectionProperty(User::class, 'currentAccountId');
        $this->hadCurrentAccountId = $property->isInitialized();
        $this->previousCurrentAccountId = $this->hadCurrentAccountId ? $property->getValue() : null;
    }

    protected function tearDown(): void
    {
        if ($this->hadCurrentAccountId) {
            User::setCurrentAccountId($this->previousCurrentAccountId);
        }

        parent::tearDown();
    }

    public function test_recognises_verified_members_when_no_account_is_current(): void
    {
        $account = $this->seedAccount();
        $owner = $this->seedMember($account, 'owner');
        $event = $this->draftEvent($account);

        User::setCurrentAccountId(null);

        $this->assertFalse($this->policy()->shouldWithholdEventContent($event, [strtoupper($owner)]));
        $this->assertTrue($this->policy()->shouldWithholdEventContent($event, ['outsider@example.com']));
    }

    public function test_checks_the_events_account_rather_than_the_current_one(): void
    {
        $account = $this->seedAccount();
        $otherAccount = $this->seedAccount();
        $owner = $this->seedMember($account, 'owner');
        $otherOwner = $this->seedMember($otherAccount, 'other-owner');
        $event = $this->draftEvent($account);

        User::setCurrentAccountId($otherAccount->id);

        $this->assertFalse($this->policy()->shouldWithholdEventContent($event, [$owner]));
        $this->assertTrue($this->policy()->shouldWithholdEventContent($event, [$otherOwner]));
    }

    public function test_withholds_content_from_invited_unverified_and_removed_members(): void
    {
        $account = $this->seedAccount();
        $owner = $this->seedMember($account, 'owner');
        $invited = $this->seedMember($account, 'invited', status: 'INVITED');
        $unverified = $this->seedMember($account, 'unverified', verified: false);
        $removed = $this->seedMember($account, 'removed', removed: true);
        $event = $this->draftEvent($account);

        User::setCurrentAccountId(null);

        $this->assertFalse($this->policy()->shouldWithholdEventContent($event, [$owner]));
        $this->assertTrue($this->policy()->shouldWithholdEventContent($event, [$invited]));
        $this->assertTrue($this->policy()->shouldWithholdEventContent($event, [$unverified]));
        $this->assertTrue($this->policy()->shouldWithholdEventContent($event, [$removed]));
    }

    private function policy(): UnpublishedEventMailPolicy
    {
        return $this->app->make(UnpublishedEventMailPolicy::class);
    }

    private function seedAccount(): Account
    {
        return Account::forceCreate([
            'name' => 'Draft Mail Account',
            'email' => 'account-'.uniqid().'@example.com',
            'timezone' => 'UTC',
            'currency_code' => 'USD',
            'short_id' => 'acc_'.uniqid(),
            'account_verified_at' => now(),
        ]);
    }

    private function seedMember(
        Account $account,
        string $name,
        string $status = 'ACTIVE',
        bool $verified = true,
        bool $removed = false,
    ): string {
        $email = $name.'+'.uniqid().'@example.com';

        $user = User::forceCreate([
            'email' => $email,
            'first_name' => 'Team',
            'last_name' => 'Member',
            'password' => 'hash',
            'timezone' => 'UTC',
            'locale' => 'en',
            'email_verified_at' => $verified ? now() : null,
        ]);

        AccountUser::forceCreate([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'status' => $status,
            'is_account_owner' => false,
            'deleted_at' => $removed ? now() : null,
        ]);

        return $email;
    }

    private function draftEvent(Account $account): EventDomainObject
    {
        $eventId = DB::table('events')->insertGetId([
            'title' => 'Draft Event',
            'account_id' => $account->id,
            'user_id' => $account->users()->first()->id,
            'short_id' => 'evt_'.uniqid(),
            'status' => EventStatus::DRAFT->name,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new EventDomainObject)
            ->setId($eventId)
            ->setAccountId($account->id)
            ->setStatus(EventStatus::DRAFT->name);
    }
}
