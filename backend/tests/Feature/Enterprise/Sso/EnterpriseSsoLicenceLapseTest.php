<?php

namespace Tests\Feature\Enterprise\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\TestCase;

class EnterpriseSsoLicenceLapseTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private const PASSWORD = 'CorrectHorse123!';

    private User $owner;

    private int $accountId;

    private AccountSsoConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, true);

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
        config()->set('app.saas_mode_enabled', false);
        $this->fakeConnectionIssuer();

        $this->owner = User::factory()->password(self::PASSWORD)->withTwoFactor((new Google2FA)->generateSecretKey(32))->withAccount()->create();
        $this->accountId = $this->owner->accounts()->first()->id;
        $this->connection = $this->createConnection($this->accountId, ['enforce_sso' => true]);
        $this->addDomain($this->accountId, 'acme.test');
    }

    public static function lapsedLicences(): array
    {
        return [
            'expired licence' => ['expired'],
            'no licence key' => ['missing'],
        ];
    }

    #[DataProvider('lapsedLicences')]
    public function test_members_keep_signing_in_through_the_connection_and_enforcement_still_applies(string $licence): void
    {
        $this->member('grace@acme.test');
        $this->lapse($licence);

        $this->postJson('/auth/sso/discover', ['email' => 'grace@acme.test'])
            ->assertOk()
            ->assertJsonPath('data.key', 'connection-'.$this->connection->id);

        $token = $this->exchangeOidcCode($this->startConnectionFlow($this->connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED')
            ->json('data.token');
        $this->getJson('/users/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.email', 'grace@acme.test');

        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
    }

    #[DataProvider('lapsedLicences')]
    public function test_an_admin_can_exempt_a_member_but_not_require_them_again(string $licence): void
    {
        $member = $this->member('grace@acme.test');
        $accountUserId = $this->accountUserId($member, $this->accountId);
        $this->lapse($licence);

        $this->putJson("/accounts/$this->accountId/sso/members/$accountUserId", ['sso_enforced' => false], $this->ownerHeaders())
            ->assertOk();
        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])->assertOk();

        $this->putJson("/accounts/$this->accountId/sso/members/$accountUserId", ['sso_enforced' => true], $this->ownerHeaders())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
    }

    private function lapse(string $licence): void
    {
        $licence === 'expired' ? $this->useLicence(expiresAt: '2020-01-01') : $this->useLicenceKey(null);
    }

    private function member(string $email): User
    {
        $user = User::factory()->password(self::PASSWORD)->create(['email' => $email]);
        $user->accounts()->attach($this->accountId, [
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
            'sso_enforced' => true,
        ]);

        return $user;
    }

    private function ownerHeaders(): array
    {
        return $this->bearer($this->tokenFor($this->owner, $this->accountId));
    }
}
