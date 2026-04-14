<?php

namespace Tests\Feature\Http\Actions\Common\Webhooks;

use HiEvents\Services\Infrastructure\Aws\SnsSignatureVerificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SesIncomingWebhookActionTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_ROUTE = '/public/webhooks/ses';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ses.sns_verify_signature' => false]);
        config(['services.ses.suppression_enabled' => true]);
        config(['services.ses.sns_topic_arn' => null]);

        Cache::flush();
    }

    public function testBounceWebhookPersistsSuppressionRecord(): void
    {
        $payload = $this->makeBouncePayload('bounce-test@example.com', 'msg-bounce-001');

        $response = $this->postJson(self::WEBHOOK_ROUTE, $payload);

        $response->assertStatus(204);

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'bounce-test@example.com',
            'reason' => 'bounce',
            'bounce_type' => 'Permanent',
            'source' => 'ses_notification',
        ]);
    }

    public function testComplaintWebhookPersistsSuppressionRecord(): void
    {
        $payload = $this->makeComplaintPayload('complaint-test@example.com', 'msg-complaint-001');

        $response = $this->postJson(self::WEBHOOK_ROUTE, $payload);

        $response->assertStatus(204);

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'complaint-test@example.com',
            'reason' => 'complaint',
            'source' => 'ses_notification',
        ]);
    }

    public function testInvalidJsonReturns400(): void
    {
        $response = $this->call('POST', self::WEBHOOK_ROUTE, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not valid json');

        $response->assertStatus(400);
    }

    public function testDuplicateMessageIsIdempotent(): void
    {
        $payload = $this->makeBouncePayload('duplicate-test@example.com', 'msg-dup-001');

        $first = $this->postJson(self::WEBHOOK_ROUTE, $payload);
        $first->assertStatus(204);

        $second = $this->postJson(self::WEBHOOK_ROUTE, $payload);
        $second->assertStatus(204);

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'duplicate-test@example.com',
        ]);
        $this->assertEquals(
            1,
            \DB::table('email_suppressions')->where('email', 'duplicate-test@example.com')->count(),
            'Duplicate SNS message should not create a second suppression record',
        );
    }

    private function makeBouncePayload(string $email, string $messageId): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => $messageId,
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-bounces',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [
                        ['emailAddress' => $email],
                    ],
                ],
            ]),
            'Timestamp' => '2026-04-11T00:00:00.000Z',
        ];
    }

    private function makeComplaintPayload(string $email, string $messageId): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => $messageId,
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-bounces',
            'Message' => json_encode([
                'notificationType' => 'Complaint',
                'complaint' => [
                    'complaintFeedbackType' => 'abuse',
                    'complainedRecipients' => [
                        ['emailAddress' => $email],
                    ],
                ],
            ]),
            'Timestamp' => '2026-04-11T00:00:00.000Z',
        ];
    }
}
