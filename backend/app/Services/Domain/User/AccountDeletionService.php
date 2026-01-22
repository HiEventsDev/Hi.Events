<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\User;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountUser;
use HiEvents\Models\Event;
use HiEvents\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

class AccountDeletionService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly Hasher $hasher,
    )
    {
    }

    /**
     * @throws RuntimeException
     */
    public function deleteUserAccount(User $user, string $confirmationWord, string $password): void
    {
        $this->assertConfirmationWord($confirmationWord);
        $this->assertPassword($user, $password);
        $this->assertNoOrphanedAccounts($user);

        $this->databaseManager->transaction(function () use ($user): void {
            // Remove memberships/pivot rows.
            AccountUser::query()
                ->where('user_id', $user->id)
                ->forceDelete();

            // Scrub personal data while keeping NOT NULL constraints valid.
            $anonEmail = sprintf('deleted+%d@%s', $user->id, 'example.invalid');

            $user->forceFill([
                'email' => $anonEmail,
                'pending_email' => null,
                'first_name' => 'Deleted',
                'last_name' => null,
                'timezone' => Config::get('app.default_timezone', 'UTC') ?: 'UTC',
                'locale' => Config::get('app.locale', 'en'),
                'email_verified_at' => null,
                'remember_token' => null,
                'marketing_opted_in_at' => null,
                // Prevent future logins even if soft-deletes are bypassed.
                'password' => $this->hasher->make(Str::random(64)),
            ])->save();

            // Soft-delete to preserve FK integrity (audit logs etc.)
            $user->delete();
        });
    }

    private function assertConfirmationWord(string $confirmationWord): void
    {
        if (strtoupper(trim($confirmationWord)) !== 'DELETE') {
            throw new RuntimeException(__('Please type DELETE to confirm account deletion.'), ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function assertPassword(User $user, string $password): void
    {
        if (!$this->hasher->check($password, (string)$user->password)) {
            throw new RuntimeException(__('The provided password is incorrect.'), ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function assertNoOrphanedAccounts(User $user): void
    {
        $ownedAccountIds = AccountUser::query()
            ->where('user_id', $user->id)
            ->where('is_account_owner', true)
            ->whereNull('deleted_at')
            ->pluck('account_id')
            ->unique()
            ->values();

        foreach ($ownedAccountIds as $accountId) {
            $ownerCount = AccountUser::query()
                ->where('account_id', $accountId)
                ->where('is_account_owner', true)
                ->whereNull('deleted_at')
                ->count();

            // If there are other owners, deletion won't orphan the account.
            if ($ownerCount > 1) {
                continue;
            }

            $otherMembersExist = AccountUser::query()
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->where('user_id', '!=', $user->id)
                ->whereIn('status', [UserStatus::ACTIVE->name, UserStatus::INVITED->name])
                ->exists();

            // Published = LIVE
            $publishedEventsExist = Event::query()
                ->where('account_id', $accountId)
                ->where('status', EventStatus::LIVE->name)
                ->exists();

            // Upcoming = LIVE + future start date
            $upcomingEventsExist = Event::query()
                ->where('account_id', $accountId)
                ->where('status', EventStatus::LIVE->name)
                ->whereNotNull('start_date')
                ->where('start_date', '>', now())
                ->exists();

            if ($otherMembersExist || $publishedEventsExist || $upcomingEventsExist) {
                throw new RuntimeException(
                    __('Account deletion is blocked because you are the only owner of an organization that still has other members and/or published/upcoming events. Transfer ownership or delete the organization first.'),
                    ResponseCodes::HTTP_CONFLICT
                );
            }
        }
    }
}
