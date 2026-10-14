<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Admin\LifecycleEmails;

use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LifecycleEmailAdminTest extends TestCase
{
    use DatabaseTransactions;

    private string $superAdminToken;

    private string $superAdminEmail;

    private string $adminToken;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        config(['app.saas_mode_enabled' => true]);
        Mail::fake();

        [$this->superAdminToken, , $this->superAdminEmail] = $this->makeUser('SUPERADMIN');
        [$this->adminToken, $this->accountId] = $this->makeUser('ADMIN');
    }

    public function test_every_lifecycle_endpoint_is_hidden_outside_saas_mode(): void
    {
        config(['app.saas_mode_enabled' => false]);
        $headers = $this->headers($this->superAdminToken);

        $this->getJson('/admin/lifecycle-emails', $headers)->assertNotFound();
        $this->putJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE', [], $this->headers($this->superAdminToken))->assertNotFound();
        $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/preview', $this->content(), $this->headers($this->superAdminToken))->assertNotFound();
        $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/test', $this->content(), $this->headers($this->superAdminToken))->assertNotFound();

        $this->assertDatabaseCount('lifecycle_email_templates', 0);
        Mail::assertNothingSent();
    }

    public function test_the_account_detail_omits_lifecycle_emails_outside_saas_mode(): void
    {
        config(['app.saas_mode_enabled' => false]);

        $this->getJson("/admin/accounts/{$this->accountId}", $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonMissingPath('data.lifecycle_nudges');
    }

    public function test_the_account_detail_includes_lifecycle_emails_in_saas_mode(): void
    {
        $this->getJson("/admin/accounts/{$this->accountId}", $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.lifecycle_nudges', []);
    }

    public function test_an_account_admin_cannot_use_the_endpoints(): void
    {
        $this->getJson('/admin/lifecycle-emails', $this->headers($this->adminToken))
            ->assertForbidden();
        $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/test', $this->content(), $this->headers($this->adminToken))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_a_superadmin_lists_saves_previews_and_tests_a_template(): void
    {
        $this->getJson('/admin/lifecycle-emails', $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.enabled_at', null);

        $this->putJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE', [
            ...$this->content(),
            'body' => '<p>Hi {{ first_name }}</p><script>alert(1)</script>',
            'enabled' => true,
        ], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.is_customised', true)
            ->assertJsonPath('data.body', '<p>Hi {{ first_name }}</p>');

        $this->assertNotNull(DB::table('lifecycle_email_templates')->where('nudge_type', 'EVENT_DRAFT_STALE')->value('enabled_at'));

        $preview = $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/preview', $this->content(), $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.subject', 'Summer Rooftop Sessions needs you');
        $this->assertMatchesRegularExpression('#<a href="\#"[^>]*>Unsubscribe</a>#', $preview->json('data.html'));

        config(['mail.lifecycle.mailer' => 'array']);
        $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/test', $this->content(), $this->headers($this->superAdminToken))
            ->assertNoContent();

        Mail::assertSent(LifecycleNudgeMail::class, fn (LifecycleNudgeMail $mail) => $mail->hasTo($this->superAdminEmail));
    }

    public function test_a_test_email_is_refused_without_a_lifecycle_mailer(): void
    {
        config(['mail.lifecycle.mailer' => null]);

        $this->postJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE/test', $this->content(), $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        Mail::assertNothingSent();
    }

    public function test_invalid_liquid_is_a_validation_error(): void
    {
        $this->putJson('/admin/lifecycle-emails/EVENT_DRAFT_STALE', [
            ...$this->content(),
            'subject' => '{% if %}',
            'enabled' => false,
        ], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['subject']);
    }

    public function test_an_unknown_nudge_type_is_not_found(): void
    {
        $this->putJson('/admin/lifecycle-emails/EVENT_DRAFT_UNVERIFIED', [...$this->content(), 'enabled' => true], $this->headers($this->superAdminToken))
            ->assertNotFound();
    }

    public function test_the_list_reports_delivery_outcomes_for_each_nudge_type(): void
    {
        $this->insertNudge('EVENT_DRAFT_STALE', 'pm-opened', [
            'delivered_at' => now(), 'first_opened_at' => now(), 'first_clicked_at' => now(),
        ]);
        $this->insertNudge('EVENT_DRAFT_STALE', 'pm-bounced', ['bounced_at' => now(), 'bounce_type' => 'HARD']);
        $this->insertNudge('EVENT_DRAFT_STALE', null, null);
        $this->insertNudge('NO_EVENT_CREATED', 'pm-delivered', ['delivered_at' => now()]);

        $emails = collect($this->getJson('/admin/lifecycle-emails', $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data'))
            ->keyBy('nudge_type');

        $this->assertSame(
            ['sent_total' => 3, 'delivered_total' => 1, 'bounced_total' => 1, 'opened_total' => 1, 'clicked_total' => 1],
            collect($emails['EVENT_DRAFT_STALE'])->only(['sent_total', 'delivered_total', 'bounced_total', 'opened_total', 'clicked_total'])->all(),
        );
        $this->assertSame(1, $emails['NO_EVENT_CREATED']['delivered_total']);
        $this->assertSame(0, $emails['NO_EVENT_CREATED']['opened_total']);
        $this->assertSame(0, $emails['LIVE_EVENT_NO_SALES']['sent_total']);
    }

    private function insertNudge(string $nudgeType, ?string $providerMessageId, ?array $delivery): void
    {
        $user = User::factory()->withAccount()->create();

        DB::table('account_lifecycle_nudges')->insert([
            'account_id' => $user->accounts()->first()->id,
            'nudge_type' => $nudgeType,
            'provider_message_id' => $providerMessageId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($delivery !== null) {
            DB::table('email_deliveries')->insert([
                'category' => 'lifecycle_nudge',
                'provider_message_id' => $providerMessageId,
                'created_at' => now(),
                'updated_at' => now(),
                ...$delivery,
            ]);
        }
    }

    private function content(): array
    {
        return [
            'subject' => '{{ event.title }} needs you',
            'body' => '<p>Hi {{ first_name }}</p>',
            'cta_label' => 'Open',
        ];
    }

    private function makeUser(string $role): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('account_users')->where('user_id', $user->id)->update(['role' => $role]);

        return [JWTAuth::claims(['account_id' => $accountId])->fromUser($user), $accountId, $user->email];
    }

    private function headers(string $token): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => "Bearer $token", 'Accept' => 'application/json'];
    }
}
