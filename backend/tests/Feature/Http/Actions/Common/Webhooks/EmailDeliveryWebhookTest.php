<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Common\Webhooks;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailDeliveryWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'postmark-secret';

    private const URL = '/public/webhooks/email-delivery/postmark';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.postmark.webhook_secret' => self::SECRET]);
    }

    public function test_records_a_delivery_against_the_sent_email(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'Delivery', 'MessageID' => 'pm-1', 'DeliveredAt' => '2026-10-13T10:00:00Z'])
            ->assertStatus(200);

        $this->assertSame('2026-10-13 10:00:00', DB::table('email_deliveries')->where('id', $id)->value('delivered_at'));
    }

    public function test_keeps_the_first_occurrence_when_an_event_repeats(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'Open', 'MessageID' => 'pm-1', 'ReceivedAt' => '2026-10-13T10:00:00Z']);
        $this->postWebhook(['RecordType' => 'Open', 'MessageID' => 'pm-1', 'ReceivedAt' => '2026-10-13T11:00:00Z']);

        $this->assertSame('2026-10-13 10:00:00', DB::table('email_deliveries')->where('id', $id)->value('first_opened_at'));
    }

    public function test_a_permanent_bounce_replaces_an_earlier_transient_one(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'Bounce', 'MessageID' => 'pm-1', 'Inactive' => false, 'BouncedAt' => '2026-10-13T10:00:00Z']);
        $this->postWebhook(['RecordType' => 'Bounce', 'MessageID' => 'pm-1', 'Inactive' => true, 'BouncedAt' => '2026-10-13T11:00:00Z']);

        $row = DB::table('email_deliveries')->where('id', $id)->first();
        $this->assertSame('PERMANENT', $row->bounce_type);
        $this->assertSame('2026-10-13 11:00:00', $row->bounced_at);
    }

    public function test_a_transient_bounce_never_downgrades_a_permanent_one(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'Bounce', 'MessageID' => 'pm-1', 'Inactive' => true, 'BouncedAt' => '2026-10-13T10:00:00Z']);
        $this->postWebhook(['RecordType' => 'Bounce', 'MessageID' => 'pm-1', 'Inactive' => false, 'BouncedAt' => '2026-10-13T11:00:00Z']);

        $row = DB::table('email_deliveries')->where('id', $id)->first();
        $this->assertSame('PERMANENT', $row->bounce_type);
        $this->assertSame('2026-10-13 10:00:00', $row->bounced_at);
    }

    public function test_records_spam_complaints_and_clicks(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'SpamComplaint', 'MessageID' => 'pm-1', 'BouncedAt' => '2026-10-13T10:00:00Z']);
        $this->postWebhook(['RecordType' => 'Click', 'MessageID' => 'pm-1', 'ReceivedAt' => '2026-10-13T09:00:00Z']);

        $row = DB::table('email_deliveries')->where('id', $id)->first();
        $this->assertSame('2026-10-13 10:00:00', $row->complained_at);
        $this->assertSame('2026-10-13 09:00:00', $row->first_clicked_at);
    }

    public function test_only_updates_the_matching_email(): void
    {
        $matching = $this->seedDelivery('pm-1');
        $other = $this->seedDelivery('pm-2');

        $this->postWebhook(['RecordType' => 'Delivery', 'MessageID' => 'pm-1', 'DeliveredAt' => '2026-10-13T10:00:00Z']);

        $this->assertNotNull(DB::table('email_deliveries')->where('id', $matching)->value('delivered_at'));
        $this->assertNull(DB::table('email_deliveries')->where('id', $other)->value('delivered_at'));
    }

    public function test_an_unknown_message_or_record_type_is_accepted_and_ignored(): void
    {
        $this->postWebhook(['RecordType' => 'Delivery', 'MessageID' => 'never-sent', 'DeliveredAt' => '2026-10-13T10:00:00Z'])
            ->assertStatus(200);
        $this->postWebhook(['RecordType' => 'SubscriptionChange', 'MessageID' => 'never-sent'])
            ->assertStatus(200);
    }

    public function test_rejects_a_wrong_secret(): void
    {
        $id = $this->seedDelivery('pm-1');

        $this->postWebhook(['RecordType' => 'Delivery', 'MessageID' => 'pm-1'], 'wrong')
            ->assertStatus(401);

        $this->assertNull(DB::table('email_deliveries')->where('id', $id)->value('delivered_at'));
    }

    public function test_is_not_found_when_no_secret_is_configured(): void
    {
        config(['services.postmark.webhook_secret' => null]);

        $this->postWebhook(['RecordType' => 'Delivery', 'MessageID' => 'pm-1'])
            ->assertStatus(404);
    }

    public function test_is_not_found_for_an_unsupported_provider(): void
    {
        $this->call('POST', '/public/webhooks/email-delivery/ses', server: $this->authServer(self::SECRET), content: '{}')
            ->assertStatus(404);
    }

    private function postWebhook(array $payload, string $password = self::SECRET)
    {
        return $this->call(
            'POST',
            self::URL,
            server: $this->authServer($password) + ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload),
        );
    }

    private function authServer(string $password): array
    {
        return ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('postmark:'.$password)];
    }

    private function seedDelivery(string $messageId): int
    {
        return DB::table('email_deliveries')->insertGetId([
            'category' => 'order_summary',
            'provider_message_id' => $messageId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
