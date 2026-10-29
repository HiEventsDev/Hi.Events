<?php

namespace Tests\Feature\Console;

use HiEvents\Models\Account;
use HiEvents\Models\AccountUser;
use HiEvents\Models\Order;
use HiEvents\Models\Organizer;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteSeptember10SignupWaveCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTED_ACCOUNT_ID = 9874;

    private const SECOND_LISTED_ACCOUNT_ID = 9875;

    private const THIRD_LISTED_ACCOUNT_ID = 11964;

    public function test_without_force_it_reports_and_deletes_nothing(): void
    {
        [$waveUser, $waveAccount] = $this->createOwner(self::LISTED_ACCOUNT_ID, 'wave@example.com', 'Asia/Jakarta', '2026-09-10 05:00');
        [, $buyerAccount] = $this->createOwner(self::SECOND_LISTED_ACCOUNT_ID, 'buyer@example.com', 'Asia/Jakarta', '2026-09-10 05:10');
        $this->createOrder($buyerAccount);

        $this->artisan('accounts:delete-2026-09-10-signups')
            ->expectsOutputToContain('1 of the 1326 listed accounts can be deleted, 1 skipped, 1324 already deleted or no longer owned by a 2026-09-10 signup.')
            ->expectsOutputToContain('Has orders')
            ->expectsOutputToContain('Nothing was deleted.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $waveUser->id]);
        $this->assertDatabaseHas('accounts', ['id' => $waveAccount->id]);
    }

    public function test_force_deletes_only_listed_accounts_that_still_pass_every_check(): void
    {
        [$waveUser, $waveAccount] = $this->createOwner(self::LISTED_ACCOUNT_ID, 'wave@example.com', 'Asia/Jakarta', '2026-09-10 05:00');

        [$buyerUser, $buyerAccount] = $this->createOwner(self::SECOND_LISTED_ACCOUNT_ID, 'buyer@example.com', 'Asia/Jakarta', '2026-09-10 05:10');
        $this->createOrder($buyerAccount);

        [$parisUser, $parisAccount] = $this->createOwner(self::THIRD_LISTED_ACCOUNT_ID, 'paris@example.com', 'Europe/Paris', '2026-09-10 05:20');

        [$unlistedUser, $unlistedAccount] = $this->createOwner(null, 'unlisted@example.com', 'Asia/Jakarta', '2026-09-10 05:30');

        $this->artisan('accounts:delete-2026-09-10-signups', ['--force' => true])
            ->expectsOutputToContain('1 of the 1326 listed accounts can be deleted, 1 skipped, 1324 already deleted')
            ->expectsOutputToContain('Deleted 1 account(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $waveUser->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $waveAccount->id]);
        $this->assertDatabaseMissing('organizers', ['account_id' => $waveAccount->id]);

        foreach ([$buyerUser, $parisUser, $unlistedUser] as $survivor) {
            $this->assertDatabaseHas('users', ['id' => $survivor->id, 'deleted_at' => null]);
        }

        foreach ([$buyerAccount, $parisAccount, $unlistedAccount] as $survivingAccount) {
            $this->assertDatabaseHas('accounts', ['id' => $survivingAccount->id, 'deleted_at' => null]);
        }
    }

    public function test_limit_caps_how_many_accounts_one_run_deletes(): void
    {
        $this->createOwner(self::LISTED_ACCOUNT_ID, 'first@example.com', 'Asia/Jakarta', '2026-09-10 05:00');
        $this->createOwner(self::SECOND_LISTED_ACCOUNT_ID, 'second@example.com', 'Asia/Jakarta', '2026-09-10 05:01');

        $this->artisan('accounts:delete-2026-09-10-signups', ['--force' => true, '--limit' => 1])
            ->expectsOutputToContain('Deleted 1 account(s).')
            ->expectsOutputToContain('1 account(s) left. Run the command again to continue.')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->whereIn('email', ['first@example.com', 'second@example.com'])->count());
    }

    /**
     * @return array{0: User, 1: Account}
     */
    private function createOwner(?int $accountId, string $email, string $timezone, string $signedUpAt): array
    {
        $user = User::forceCreate([
            'email' => $email,
            'first_name' => 'Signup',
            'last_name' => 'Wave',
            'password' => 'hash',
            'timezone' => $timezone,
            'created_at' => $signedUpAt,
        ]);

        $account = $accountId === null
            ? Account::forceCreate([
                'name' => $email,
                'email' => $email,
                'timezone' => $timezone,
                'currency_code' => 'USD',
                'short_id' => 'acc_'.Str::random(10),
                'created_at' => $signedUpAt,
            ])
            : $this->createAccountWithId($accountId, $email, $timezone, $signedUpAt);

        AccountUser::forceCreate([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'is_account_owner' => true,
        ]);

        Organizer::forceCreate([
            'name' => $email,
            'account_id' => $account->id,
            'email' => $email,
            'currency' => 'USD',
            'timezone' => $timezone,
            'status' => 'LIVE',
        ]);

        return [$user, $account];
    }

    private function createAccountWithId(int $accountId, string $email, string $timezone, string $createdAt): Account
    {
        DB::insert(
            'insert into accounts (id, name, email, timezone, currency_code, short_id, created_at, updated_at)
                overriding system value values (?, ?, ?, ?, ?, ?, ?, ?)',
            [$accountId, $email, $email, $timezone, 'USD', 'acc_'.Str::random(10), $createdAt, $createdAt],
        );

        return Account::findOrFail($accountId);
    }

    private function createOrder(Account $account): void
    {
        $eventId = DB::table('events')->insertGetId([
            'title' => 'Event',
            'account_id' => $account->id,
            'organizer_id' => Organizer::query()->where('account_id', $account->id)->value('id'),
            'user_id' => AccountUser::query()->where('account_id', $account->id)->value('user_id'),
            'short_id' => 'evt_'.Str::random(10),
            'status' => 'DRAFT',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => '2026-09-10 06:00',
            'updated_at' => '2026-09-10 06:00',
        ]);

        Order::forceCreate([
            'event_id' => $eventId,
            'short_id' => 'ord_'.Str::random(10),
            'public_id' => Str::random(20),
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
            'total_before_additions' => 0,
            'total_gross' => 0,
            'currency' => 'USD',
            'status' => 'COMPLETED',
        ]);
    }
}
