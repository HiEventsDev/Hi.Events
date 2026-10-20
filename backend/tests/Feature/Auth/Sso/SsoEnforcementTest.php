<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Enterprise\Sso\Mail\SsoBreakGlassSignInMail;
use HiEvents\Mail\User\ForgotPassword;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class SsoEnforcementTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;
    use UsesApiKeys;

    private const PASSWORD = 'CorrectHorse123!';

    private string $ownerSecret;

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
        $this->ownerSecret = (new Google2FA)->generateSecretKey(32);
    }

    public function test_enforcement_needs_an_active_connection_a_verified_domain_and_an_owner_with_two_factor(): void
    {
        $owner = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $connection = $this->createConnection($accountId, ['status' => 'INACTIVE']);
        $headers = $this->bearer($this->tokenFor($owner, $accountId));

        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enforce_sso');

        $connection->update(['status' => 'ACTIVE']);
        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable();

        $this->addDomain($accountId, 'acme.test');
        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enforce_sso');

        $this->assertFalse((bool) $connection->fresh()->enforce_sso);
    }

    public function test_enabling_snapshots_members_on_verified_domains_and_ends_their_other_sessions(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $enforced = $this->member($accountId, 'grace@acme.test');
        $exempt = $this->member($accountId, 'contractor@acme.test');
        $outside = $this->member($accountId, 'agency@agency.test');

        $preview = $this->getJson("/accounts/$accountId/sso/enforcement", $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();
        $this->assertSame(2, $preview->json('data.affected_count'));
        $this->assertSame(1, $preview->json('data.outside_domain_count'));

        $this->putJson("/accounts/$accountId/sso/enforcement", [
            'enabled' => true,
            'exempt_account_user_ids' => [$this->accountUserId($exempt, $accountId)],
        ], $this->bearer($this->tokenFor($owner, $accountId)))->assertOk()->assertJsonPath('data.enforce_sso', true);

        $this->assertTrue($this->isEnforced($enforced, $accountId));
        $this->assertFalse($this->isEnforced($exempt, $accountId));
        $this->assertFalse($this->isEnforced($outside, $accountId));
        $this->assertFalse($this->isEnforced($owner, $accountId));
        $this->assertNotNull($enforced->fresh()->tokens_valid_after);
        $this->assertNull($exempt->fresh()->tokens_valid_after);
    }

    public function test_a_session_ended_by_requiring_sso_says_why(): void
    {
        [$owner, $accountId] = $this->enforcingAccount(enabled: false);
        $member = $this->member($accountId, 'grace@acme.test');
        $memberToken = $this->tokenFor($member, $accountId);

        $this->travel(5)->seconds();
        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();

        $this->getJson('/users/me', $this->bearer($memberToken))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
    }

    public function test_enabling_removes_a_members_api_keys_in_this_account_only(): void
    {
        [$owner, $accountId] = $this->enforcingAccount(enabled: false);
        $member = $this->member($accountId, 'grace@acme.test');
        $otherAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $member->accounts()->attach($otherAccountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);
        $this->createApiKey($member, $accountId, name: 'Here');
        $this->createApiKey($member, $otherAccountId, name: 'Elsewhere');

        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();

        $this->assertSame(['Elsewhere'], DB::table('personal_access_tokens')->where('tokenable_id', $member->id)->pluck('name')->all());
    }

    public function test_an_enforced_member_cannot_sign_in_with_a_password_but_can_through_the_connection(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test', enforced: true);

        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED')
            ->assertJsonPath('context.email', 'grace@acme.test');

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');
    }

    public function test_a_member_who_owns_another_account_keeps_password_sign_in_because_the_connection_cannot_link_them(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $alice = $this->member($accountId, 'alice@acme.test', enforced: true);
        $ownAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $alice->accounts()->attach($ownAccountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);
        $this->member($accountId, 'grace@acme.test', enforced: true);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-alice', 'alice@acme.test'))
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');

        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertOk();
        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])
            ->assertJsonPath('error_code', 'SSO_REQUIRED');

        $members = collect($this->getJson("/accounts/$accountId/sso/enforcement", $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk()
            ->json('data.members'))->keyBy('email');
        $this->assertFalse($members['alice@acme.test']['sso_available']);
        $this->assertTrue($members['grace@acme.test']['sso_available']);
    }

    public function test_an_exempt_member_and_a_member_outside_the_domains_keep_password_sign_in(): void
    {
        [, $accountId] = $this->enforcingAccount();
        $this->member($accountId, 'contractor@acme.test', enforced: false);
        $this->member($accountId, 'agency@agency.test', enforced: true);

        $this->postJson('/auth/login', ['email' => 'contractor@acme.test', 'password' => self::PASSWORD])->assertOk();
        $this->postJson('/auth/login', ['email' => 'agency@agency.test', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_an_enforced_members_password_session_is_rejected_while_a_connection_session_is_accepted(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test', enforced: true);

        $this->getJson('/users/me', $this->bearer($this->tokenFor($member, $accountId)))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->json('data.token');

        $this->getJson('/users/me', $this->bearer($token))->assertOk();
    }

    public function test_a_connection_session_ends_when_the_connection_is_turned_off_or_deleted(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $this->member($accountId, 'grace@acme.test');

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->json('data.token');
        $this->getJson('/users/me', $this->bearer($token))->assertOk();

        DB::table('account_sso_connections')->where('id', $connection->id)->update(['status' => 'INACTIVE']);
        $this->getJson('/users/me', $this->bearer($token))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_REVOKED');

        DB::table('account_sso_connections')->where('id', $connection->id)->update(['status' => 'ACTIVE']);
        $this->getJson('/users/me', $this->bearer($token))->assertOk();

        DB::table('account_sso_connections')->where('id', $connection->id)->delete();
        $this->getJson('/users/me', $this->bearer($token))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_REVOKED');
    }

    public function test_enforcement_stops_when_the_connection_is_off_or_the_domain_is_suspended(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test', enforced: true);

        DB::table('account_sso_domains')->where('account_id', $accountId)->update(['failing_since' => now()->subDays(8)]);
        $this->getJson('/users/me', $this->bearer($this->tokenFor($member, $accountId)))->assertOk();

        DB::table('account_sso_domains')->where('account_id', $accountId)->update(['failing_since' => null]);
        $connection->update(['status' => 'INACTIVE']);
        $this->getJson('/users/me', $this->bearer($this->tokenFor($member, $accountId)))->assertOk();
    }

    public function test_the_owner_always_answers_a_fresh_two_factor_challenge_and_admins_are_told(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $admin = $this->member($accountId, 'admin@acme.test', enforced: true, role: Role::ADMIN);
        $deviceToken = app(TrustedDeviceService::class)->trust($owner->id, 'Browser', '127.0.0.1');

        $challenge = $this->withCredentials()
            ->withUnencryptedCookie(TrustedDeviceService::COOKIE_NAME, $deviceToken)
            ->postJson('/auth/login', ['email' => $owner->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJson(['two_factor_required' => true])
            ->json('two_factor_challenge_token');

        $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA)->getCurrentOtp($this->ownerSecret),
        ])->assertOk()->assertJsonStructure(['token']);

        Mail::assertQueued(SsoBreakGlassSignInMail::class, fn (SsoBreakGlassSignInMail $mail) => $mail->hasTo($admin->email)
            && $mail->envelope()->subject === 'The account owner signed in without single sign-on');
        Mail::assertQueued(SsoBreakGlassSignInMail::class, fn (SsoBreakGlassSignInMail $mail) => $mail->hasTo($owner->email)
            && $mail->envelope()->subject === 'You signed in without single sign-on');
    }

    public function test_the_owner_is_told_two_factor_must_stay_on_while_the_account_requires_sso(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $accountName = DB::table('accounts')->where('id', $accountId)->value('name');

        $this->getJson('/users/me/two-factor', $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk()
            ->assertJsonPath('data.required_by_accounts', [])
            ->assertJsonPath('data.required_by_sso_accounts', [$accountName]);
    }

    public function test_support_cannot_reset_two_factor_for_the_owner_of_an_account_that_requires_sso(): void
    {
        [$owner] = $this->enforcingAccount();
        $superadmin = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $superadminAccountId = $superadmin->accounts()->first()->id;
        $superadmin->accounts()->updateExistingPivot($superadminAccountId, ['role' => Role::SUPERADMIN->name]);

        $this->postJson("/admin/users/{$owner->id}/two-factor/reset", [], $this->bearer($this->tokenFor($superadmin, $superadminAccountId)))
            ->assertConflict();

        $this->assertNotNull($owner->fresh()->two_factor_secret);
    }

    public function test_the_owner_cannot_use_the_backup_sign_in_without_two_factor(): void
    {
        [$owner] = $this->enforcingAccount();
        $owner->update(['two_factor_confirmed_at' => null, 'two_factor_secret' => null]);

        $this->postJson('/auth/login', ['email' => $owner->email, 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
        Mail::assertNotQueued(SsoBreakGlassSignInMail::class);
    }

    public function test_forgot_password_sends_nothing_to_an_enforced_member(): void
    {
        [, $accountId] = $this->enforcingAccount();
        $this->member($accountId, 'grace@acme.test', enforced: true);
        $this->member($accountId, 'contractor@acme.test', enforced: false);

        $enforced = $this->postJson('/auth/forgot-password', ['email' => 'grace@acme.test']);
        $exempt = $this->postJson('/auth/forgot-password', ['email' => 'contractor@acme.test']);

        $this->assertSame($exempt->status(), $enforced->status());
        Mail::assertQueued(ForgotPassword::class, 1);
        Mail::assertQueued(ForgotPassword::class, fn (ForgotPassword $mail) => $mail->hasTo('contractor@acme.test'));
    }

    public function test_me_says_whether_a_password_can_be_set_so_enforced_members_are_not_offered_one(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $this->member($accountId, 'grace@acme.test', enforced: true);
        $exempt = $this->member($accountId, 'contractor@acme.test', enforced: false);

        $this->getJson('/users/me', $this->headersFor($exempt, $accountId))
            ->assertOk()
            ->assertJsonPath('data.password_reset_allowed', true);

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        $this->getJson('/users/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.password_reset_allowed', false);
    }

    public function test_an_enforced_member_cannot_move_their_email_off_the_verified_domains(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $this->member($accountId, 'grace@acme.test', enforced: true);
        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->json('data.token');

        $this->putJson('/users/me', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@gmail.com',
            'timezone' => 'UTC',
            'locale' => 'en',
            'confirmation_password' => self::PASSWORD,
        ], $this->bearer($token))->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_people_invited_while_enforcement_is_on_are_enforced_from_the_start(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();

        $this->postJson('/users', [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'new.hire@acme.test',
            'role' => 'ORGANIZER',
        ], $this->bearer($this->tokenFor($owner, $accountId)))->assertCreated();

        $invited = User::where('email', 'new.hire@acme.test')->firstOrFail();
        $this->assertTrue($this->isEnforced($invited, $accountId));
    }

    public function test_an_admin_can_exempt_a_member_and_require_them_again(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test', enforced: true);
        $outside = $this->member($accountId, 'agency@agency.test');
        $accountUserId = $this->accountUserId($member, $accountId);

        $this->putJson("/accounts/$accountId/sso/members/$accountUserId", ['sso_enforced' => false], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();
        $this->assertFalse($this->isEnforced($member, $accountId));
        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])->assertOk();

        $this->putJson("/accounts/$accountId/sso/members/$accountUserId", ['sso_enforced' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();
        $this->assertTrue($this->isEnforced($member, $accountId));
        $this->assertNotNull($member->fresh()->tokens_valid_after);

        $this->putJson("/accounts/$accountId/sso/members/{$this->accountUserId($outside, $accountId)}", ['sso_enforced' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable();
    }

    public function test_the_connection_cannot_be_turned_off_or_deleted_while_enforcing(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();

        $this->putJson("/accounts/$accountId/sso/connection/status", ['status' => 'INACTIVE'], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable();
        $this->deleteJson("/accounts/$accountId/sso/connection", [], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable();

        $verifiedDomainId = DB::table('account_sso_domains')->where('account_id', $accountId)->value('id');
        $this->deleteJson("/accounts/$accountId/sso/domains/$verifiedDomainId", [], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $pendingDomainId = $this->addDomain($accountId, 'pending.test', verified: false);
        $this->deleteJson("/accounts/$accountId/sso/domains/$pendingDomainId", [], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertNoContent();
    }

    public function test_the_team_list_shows_how_each_member_signs_in(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $enforced = $this->member($accountId, 'grace@acme.test', enforced: true);
        $exempt = $this->member($accountId, 'contractor@acme.test', enforced: false);
        $outside = $this->member($accountId, 'agency@agency.test');

        $users = collect($this->getJson('/users', $this->bearer($this->tokenFor($owner, $accountId)))->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertSame('SSO', $users[$enforced->id]['sso_sign_in']);
        $this->assertSame('EXEMPT', $users[$exempt->id]['sso_sign_in']);
        $this->assertSame('PASSWORD', $users[$outside->id]['sso_sign_in']);
        $this->assertSame('PASSWORD', $users[$owner->id]['sso_sign_in']);
    }

    public function test_requiring_sso_leaves_members_the_connection_cannot_sign_in_signed_in_with_their_api_keys(): void
    {
        [$owner, $accountId] = $this->enforcingAccount(enabled: false);
        $alice = $this->member($accountId, 'alice@acme.test');
        $aliceAccountId = $this->giveOwnAccount($alice);
        $staff = $this->member($accountId, 'staff@acme.test', role: Role::SUPERADMIN);
        $grace = $this->member($accountId, 'grace@acme.test');
        foreach ([$alice, $staff, $grace] as $member) {
            $this->createApiKey($member, $accountId, name: 'Here');
        }
        $aliceToken = $this->tokenFor($alice, $accountId);
        $aliceOwnAccountToken = $this->tokenFor($alice, $aliceAccountId);
        $staffToken = $this->tokenFor($staff, $accountId);

        $preview = $this->previewFor($owner, $accountId);
        $this->assertFalse($preview['alice@acme.test']['sso_available']);
        $this->assertFalse($preview['staff@acme.test']['sso_available']);
        $this->assertTrue($preview['grace@acme.test']['sso_available']);

        $this->travel(5)->seconds();
        $this->putJson("/accounts/$accountId/sso/enforcement", ['enabled' => true], $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk();

        foreach ([$alice, $staff] as $member) {
            $this->assertTrue($this->isEnforced($member, $accountId));
            $this->assertNull($member->fresh()->tokens_valid_after);
            $this->assertSame(['Here'], $this->apiKeyNames($member));
        }
        $this->getJson('/users/me', $this->bearer($aliceToken))->assertOk();
        $this->getJson('/users/me', $this->bearer($aliceOwnAccountToken))->assertOk();
        $this->getJson('/users/me', $this->bearer($staffToken))->assertOk();
        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])->assertOk();

        $this->assertNotNull($grace->fresh()->tokens_valid_after);
        $this->assertSame([], $this->apiKeyNames($grace));

        $signIn = $this->teamSignInFor($owner, $accountId);
        $this->assertSame('PASSWORD', $signIn[$alice->id]);
        $this->assertSame('PASSWORD', $signIn[$staff->id]);
        $this->assertSame('SSO', $signIn[$grace->id]);
    }

    public function test_requiring_sso_for_one_member_the_connection_cannot_sign_in_keeps_their_sessions(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $alice = $this->member($accountId, 'alice@acme.test');
        $this->giveOwnAccount($alice);
        $staff = $this->member($accountId, 'staff@acme.test', role: Role::SUPERADMIN);

        foreach ([$alice, $staff] as $member) {
            $this->createApiKey($member, $accountId, name: 'Here');

            $this->putJson("/accounts/$accountId/sso/members/{$this->accountUserId($member, $accountId)}", ['sso_enforced' => true], $this->bearer($this->tokenFor($owner, $accountId)))
                ->assertOk();

            $this->assertTrue($this->isEnforced($member, $accountId));
            $this->assertNull($member->fresh()->tokens_valid_after);
            $this->assertSame(['Here'], $this->apiKeyNames($member));
        }
    }

    public function test_a_member_who_owns_another_account_moves_to_sso_once_linked_but_a_superadmin_never_does(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $alice = $this->member($accountId, 'alice@acme.test', enforced: true);
        $this->giveOwnAccount($alice);
        $staff = $this->member($accountId, 'staff@acme.test', enforced: true, role: Role::SUPERADMIN);

        $signIn = $this->teamSignInFor($owner, $accountId);
        $this->assertSame('PASSWORD', $signIn[$alice->id]);
        $this->assertSame('PASSWORD', $signIn[$staff->id]);

        foreach (['alice' => $alice, 'staff' => $staff] as $subject => $member) {
            UserIdentity::create([
                'user_id' => $member->id,
                'account_sso_connection_id' => $connection->id,
                'provider_key' => 'connection-'.$connection->id,
                'issuer' => self::CONNECTION_ISSUER,
                'subject' => 'okta-'.$subject,
            ]);
        }

        $preview = $this->previewFor($owner, $accountId);
        $this->assertTrue($preview['alice@acme.test']['sso_available']);
        $this->assertFalse($preview['staff@acme.test']['sso_available']);

        $signIn = $this->teamSignInFor($owner, $accountId);
        $this->assertSame('SSO', $signIn[$alice->id]);
        $this->assertSame('PASSWORD', $signIn[$staff->id]);

        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
        $this->postJson('/auth/login', ['email' => 'staff@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertOk();
    }

    public function test_a_personal_google_identity_does_not_count_as_a_way_into_a_workspace_connection(): void
    {
        $owner = User::factory()->password(self::PASSWORD)->withTwoFactor($this->ownerSecret)->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $this->createConnection($accountId, [
            'type' => 'GOOGLE_WORKSPACE',
            'issuer' => 'https://accounts.google.com',
            'hosted_domain' => 'acme.test',
            'enforce_sso' => true,
        ]);
        $this->addDomain($accountId, 'acme.test');

        foreach (['alice@acme.test' => 'alice.personal@gmail.com', 'bob@acme.test' => 'bob@acme.test'] as $email => $googleEmail) {
            $member = $this->member($accountId, $email, enforced: true);
            $this->giveOwnAccount($member);
            UserIdentity::create([
                'user_id' => $member->id,
                'provider_key' => 'google',
                'issuer' => 'https://accounts.google.com',
                'subject' => 'google-'.$member->id,
                'email' => $googleEmail,
            ]);
        }

        $preview = $this->previewFor($owner, $accountId);
        $this->assertFalse($preview['alice@acme.test']['sso_available']);
        $this->assertTrue($preview['bob@acme.test']['sso_available']);

        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertOk();
    }

    private function giveOwnAccount(User $user): int
    {
        $ownAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $user->accounts()->attach($ownAccountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);

        return $ownAccountId;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function previewFor(User $owner, int $accountId): array
    {
        return collect($this->getJson("/accounts/$accountId/sso/enforcement", $this->bearer($this->tokenFor($owner, $accountId)))
            ->assertOk()
            ->json('data.members'))->keyBy('email')->all();
    }

    /**
     * @return array<int, string|null>
     */
    private function teamSignInFor(User $owner, int $accountId): array
    {
        return collect($this->getJson('/users', $this->bearer($this->tokenFor($owner, $accountId)))->assertOk()->json('data'))
            ->mapWithKeys(fn (array $user) => [$user['id'] => $user['sso_sign_in'] ?? null])
            ->all();
    }

    /**
     * @return string[]
     */
    private function apiKeyNames(User $user): array
    {
        return DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->pluck('name')->all();
    }

    /**
     * @return array{0: User, 1: int, 2: AccountSsoConnection}
     */
    private function enforcingAccount(bool $enabled = true): array
    {
        $owner = User::factory()->password(self::PASSWORD)->withTwoFactor($this->ownerSecret)->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $connection = $this->createConnection($accountId, ['enforce_sso' => $enabled]);
        $this->addDomain($accountId, 'acme.test');

        return [$owner, $accountId, $connection];
    }

    private function member(int $accountId, string $email, bool $enforced = false, Role $role = Role::ORGANIZER): User
    {
        $user = User::factory()->password(self::PASSWORD)->create(['email' => $email]);
        $user->accounts()->attach($accountId, [
            'role' => $role->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
            'sso_enforced' => $enforced,
        ]);

        return $user;
    }

    private function isEnforced(User $user, int $accountId): bool
    {
        return (bool) DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $user->id])->value('sso_enforced');
    }
}
