<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Admin\EmailDeliveries;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class GetEmailDeliveryStatsTest extends TestCase
{
    use DatabaseTransactions;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('email_deliveries')->delete();
        [, $this->authToken] = $this->makeAuthenticatedUser('SUPERADMIN');
    }

    public function test_non_superadmin_cannot_read_email_delivery_stats(): void
    {
        [, $adminToken] = $this->makeAuthenticatedUser('ADMIN');

        $this->getJson('/admin/email-deliveries/stats', $this->authHeaders($adminToken))->assertStatus(403);
    }

    public function test_aggregates_deliveries_per_category_for_the_period(): void
    {
        $this->seedDelivery('order_summary', ['delivered_at' => now(), 'first_opened_at' => now(), 'first_clicked_at' => now()]);
        $this->seedDelivery('order_summary', ['delivered_at' => now(), 'bounced_at' => now(), 'bounce_type' => 'TRANSIENT']);
        $this->seedDelivery('order_summary', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT', 'complained_at' => now()]);
        $this->seedDelivery('attendee_ticket', ['delivered_at' => now()]);
        $this->seedDelivery('attendee_ticket', [], now()->subDays(40));

        $response = $this->getJson('/admin/email-deliveries/stats?period=30d', $this->authHeaders())
            ->assertStatus(200);

        $this->assertSame([
            [
                'category' => 'order_summary',
                'sent' => 3,
                'delivered' => 2,
                'permanent_bounces' => 1,
                'transient_bounces' => 1,
                'complaints' => 1,
                'opened' => 1,
                'clicked' => 1,
            ],
            [
                'category' => 'attendee_ticket',
                'sent' => 1,
                'delivered' => 1,
                'permanent_bounces' => 0,
                'transient_bounces' => 0,
                'complaints' => 0,
                'opened' => 0,
                'clicked' => 0,
            ],
        ], $response->json('data.categories'));
    }

    public function test_reports_whether_a_delivery_webhook_is_configured(): void
    {
        config(['services.postmark.webhook_secret' => null]);
        $this->getJson('/admin/email-deliveries/stats', $this->authHeaders())
            ->assertJsonPath('data.delivery_webhook_configured', false);

        config(['services.postmark.webhook_secret' => 'secret']);
        $this->getJson('/admin/email-deliveries/stats', $this->authHeaders())
            ->assertJsonPath('data.delivery_webhook_configured', true);
    }

    public function test_rejects_an_unknown_period(): void
    {
        $this->getJson('/admin/email-deliveries/stats?period=1y', $this->authHeaders())
            ->assertStatus(422);
    }

    private function seedDelivery(string $category, array $attributes, $createdAt = null): void
    {
        DB::table('email_deliveries')->insert([
            'category' => $category,
            'provider_message_id' => uniqid('pm-', true),
            'created_at' => $createdAt ?? now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function makeAuthenticatedUser(string $role): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('account_users')
            ->where('account_id', $accountId)
            ->where('user_id', $user->id)
            ->update(['role' => $role]);

        return [$user, JWTAuth::claims(['account_id' => $accountId])->fromUser($user)];
    }

    private function authHeaders(?string $token = null): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.($token ?? $this->authToken)];
    }
}
