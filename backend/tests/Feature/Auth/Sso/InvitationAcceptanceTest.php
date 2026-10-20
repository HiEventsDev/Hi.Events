<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class InvitationAcceptanceTest extends TestCase
{
    use DatabaseTransactions;
    use FakesOidcProvider;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
        $this->account = Account::factory()->verified()->create(['name' => 'Acme Events']);
        $this->fakeOidcProvider();
    }

    public function test_a_new_invitee_sets_a_password(): void
    {
        $invitee = $this->invite(User::factory()->create(['password' => null, 'email_verified_at' => null]));

        $this->getJson('/auth/invitation/'.$this->token($invitee))
            ->assertOk()
            ->assertJsonPath('data.account_name', 'Acme Events')
            ->assertJsonPath('data.requires_sign_in', false);

        $this->postJson('/auth/invitation/'.$this->token($invitee), $this->passwordPayload())->assertNoContent();

        $this->assertTrue(Hash::check('CorrectHorse123!', $invitee->fresh()->password));
        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($invitee));
    }

    public function test_an_existing_user_is_never_overwritten_by_an_invitation(): void
    {
        $existing = User::factory()->password('MyOwnPassword1!')->withAccount()->create();
        $this->invite($existing);

        $this->getJson('/auth/invitation/'.$this->token($existing))
            ->assertJsonPath('data.requires_sign_in', true);

        $this->postJson('/auth/invitation/'.$this->token($existing), $this->passwordPayload())
            ->assertConflict()
            ->assertJsonPath('error_code', 'INVITATION_SIGN_IN_REQUIRED');

        $this->assertTrue(Hash::check('MyOwnPassword1!', $existing->fresh()->password));
        $this->assertSame(UserStatus::INVITED->name, $this->membershipStatus($existing));
    }

    public function test_a_signed_in_user_joins_with_one_click(): void
    {
        $existing = User::factory()->withAccount()->create();
        $this->invite($existing);
        $token = JWTAuth::claims(['account_id' => $existing->accounts()->first()->id])->fromUser($existing);

        $this->withToken($token)->postJson('/auth/invitation/'.$this->token($existing).'/join')
            ->assertOk()
            ->assertCookie('token');

        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($existing));
    }

    public function test_someone_else_cannot_join_with_the_invitation(): void
    {
        $invitee = User::factory()->withAccount()->create();
        $this->invite($invitee);
        $intruder = User::factory()->withAccount()->create();
        $token = JWTAuth::claims(['account_id' => $intruder->accounts()->first()->id])->fromUser($intruder);

        $this->withToken($token)->postJson('/auth/invitation/'.$this->token($invitee).'/join')
            ->assertConflict();

        $this->assertSame(UserStatus::INVITED->name, $this->membershipStatus($invitee));
    }

    public function test_a_new_invitee_accepts_with_single_sign_on(): void
    {
        $invitee = $this->invite(User::factory()->create([
            'password' => null,
            'email' => 'new.member@example.com',
            'first_name' => 'Named',
            'last_name' => 'By Inviter',
            'email_verified_at' => null,
        ]));

        $flow = $this->startOidcFlow([
            'intent' => 'INVITE',
            'invitation_token' => $this->token($invitee),
            'timezone' => 'Europe/Dublin',
        ]);

        $this->exchangeOidcCode($flow, ['sub' => 'member', 'email' => 'NEW.MEMBER@example.com', 'given_name' => 'Other'])
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED')
            ->assertCookie('token');

        $invitee->refresh();
        $this->assertNull($invitee->password);
        $this->assertSame('Named', $invitee->first_name);
        $this->assertSame('Europe/Dublin', $invitee->timezone);
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($invitee));
        $this->assertDatabaseHas('user_identities', ['user_id' => $invitee->id, 'subject' => 'member']);
    }

    public function test_an_invitation_for_another_email_is_refused(): void
    {
        $invitee = $this->invite(User::factory()->create(['password' => null, 'email' => 'invited@example.com']));
        $flow = $this->startOidcFlow(['intent' => 'INVITE', 'invitation_token' => $this->token($invitee)]);

        $this->exchangeOidcCode($flow, ['sub' => 'someone', 'email' => 'someone.else@example.com'])
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_INVITE_EMAIL_MISMATCH')
            ->assertJsonPath('context.invited_email', 'invited@example.com');

        $this->assertSame(UserStatus::INVITED->name, $this->membershipStatus($invitee));
    }

    public function test_an_existing_user_must_sign_in_before_accepting_with_single_sign_on(): void
    {
        $existing = $this->invite(User::factory()->password('MyOwnPassword1!')->withAccount()->create());
        $flow = $this->startOidcFlow(['intent' => 'INVITE', 'invitation_token' => $this->token($existing)]);

        $this->exchangeOidcCode($flow, ['sub' => 'someone', 'email' => $existing->email])
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_INVITE_SIGN_IN_REQUIRED');
    }

    public function test_an_existing_user_with_a_linked_identity_accepts_by_signing_in(): void
    {
        $existing = $this->invite(User::factory()->withAccount()->create());
        UserIdentity::create([
            'user_id' => $existing->id,
            'provider_key' => 'oidc',
            'issuer' => self::OIDC_ISSUER,
            'subject' => 'linked',
            'email' => $existing->email,
        ]);
        $flow = $this->startOidcFlow(['intent' => 'INVITE', 'invitation_token' => $this->token($existing)]);

        $this->exchangeOidcCode($flow, ['sub' => 'linked', 'email' => $existing->email])
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($existing));
        $this->assertNotNull($existing->fresh()->password);
    }

    public function test_a_provider_without_an_email_cannot_accept_an_invitation(): void
    {
        $invitee = $this->invite(User::factory()->create(['password' => null, 'email' => 'invited@example.com']));
        $flow = $this->startOidcFlow(['intent' => 'INVITE', 'invitation_token' => $this->token($invitee)]);

        $this->exchangeOidcCode($flow, ['sub' => 'no-email'])
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_EMAIL_REQUIRED');

        $this->assertSame(UserStatus::INVITED->name, $this->membershipStatus($invitee));
    }

    public function test_an_invitee_who_set_a_password_before_accepting_can_still_join(): void
    {
        $invitee = $this->invite(User::factory()->password('ForgotFlowPass1!')->create());

        $this->getJson('/auth/invitation/'.$this->token($invitee))
            ->assertJsonPath('data.requires_sign_in', false);

        $this->postJson('/auth/invitation/'.$this->token($invitee), $this->passwordPayload())->assertNoContent();

        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($invitee));
    }

    public function test_an_invalid_invitation_cannot_start_single_sign_on(): void
    {
        $this->postJson('/auth/sso/oidc/start', ['intent' => 'INVITE', 'invitation_token' => 'garbage'])
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_INVITE_INVALID');
    }

    private function invite(User $user): User
    {
        $user->accounts()->attach($this->account, [
            'role' => Role::ORGANIZER->name,
            'status' => UserStatus::INVITED->name,
            'is_account_owner' => false,
        ]);

        return $user;
    }

    private function token(User $user): string
    {
        return app(EncryptedPayloadService::class)->encryptPayload([
            'user_id' => $user->id,
            'account_id' => $this->account->id,
        ]);
    }

    private function membershipStatus(User $user): string
    {
        return DB::table('account_users')
            ->where('user_id', $user->id)
            ->where('account_id', $this->account->id)
            ->value('status');
    }

    private function passwordPayload(): array
    {
        return [
            'first_name' => 'Pat',
            'last_name' => 'Member',
            'password' => 'CorrectHorse123!',
            'password_confirmation' => 'CorrectHorse123!',
            'timezone' => 'Europe/Dublin',
        ];
    }
}
