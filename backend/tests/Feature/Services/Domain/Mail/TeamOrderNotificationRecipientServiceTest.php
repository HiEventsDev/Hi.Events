<?php

namespace Tests\Feature\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Mail\TeamOrderNotificationRecipientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TeamOrderNotificationRecipientServiceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId));
    }

    public function test_recipients_carry_each_members_locale(): void
    {
        $french = $this->subscribedMember('fr');
        $english = $this->subscribedMember('en');

        $locales = collect($this->recipientsFor(null))
            ->mapWithKeys(fn (UserDomainObject $user) => [strtolower($user->getEmail()) => $user->getLocale()])
            ->sortKeys()
            ->all();

        $expected = collect([
            strtolower($french->email) => 'fr',
            strtolower($english->email) => 'en',
        ])->sortKeys()->all();

        $this->assertSame($expected, $locales);
    }

    public function test_the_excluded_address_is_skipped_regardless_of_case(): void
    {
        $member = $this->subscribedMember('fr');

        $this->assertSame([], $this->recipientsFor(strtoupper($member->email)));
    }

    /**
     * @return UserDomainObject[]
     */
    private function recipientsFor(?string $excludeEmail): array
    {
        return $this->app->make(TeamOrderNotificationRecipientService::class)->recipientsFor(
            $this->app->make(EventRepositoryInterface::class)->findById($this->eventId),
            $excludeEmail,
        );
    }

    private function subscribedMember(string $locale): User
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        DB::table('users')->where('id', $user->id)->update(['locale' => $locale]);
        DB::table('account_users')
            ->where('id', $this->accountUserId($user, $this->accountId))
            ->update(['notify_new_orders' => true]);

        return $user;
    }
}
