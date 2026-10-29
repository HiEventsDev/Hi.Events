<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Infrastructure\Email\Delivery;

use HiEvents\DomainObjects\Enums\EmailBounceType;
use HiEvents\DomainObjects\Enums\EmailDeliveryEventType;
use HiEvents\DomainObjects\Enums\EmailDeliveryProvider;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryWebhookDTO;
use HiEvents\Services\Infrastructure\Email\Delivery\PostmarkEmailDeliveryWebhookReceiver;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostmarkEmailDeliveryWebhookReceiverTest extends TestCase
{
    private const SECRET = 'postmark-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_is_configured_only_when_a_secret_is_set(): void
    {
        $this->assertTrue($this->receiver()->isConfigured());
        $this->assertFalse($this->receiver(null)->isConfigured());
        $this->assertFalse($this->receiver('')->isConfigured());
    }

    public function test_verifies_basic_auth_with_the_shared_secret_as_password(): void
    {
        $this->assertTrue($this->receiver()->verify($this->webhook('{}', 'any-user', self::SECRET)));
    }

    public function test_rejects_a_wrong_password(): void
    {
        $this->assertFalse($this->receiver()->verify($this->webhook('{}', 'any-user', 'wrong')));
    }

    public function test_rejects_a_missing_or_malformed_authorization_header(): void
    {
        $receiver = $this->receiver();

        $this->assertFalse($receiver->verify(new EmailDeliveryWebhookDTO(EmailDeliveryProvider::POSTMARK, [], '{}')));
        $this->assertFalse($receiver->verify(new EmailDeliveryWebhookDTO(EmailDeliveryProvider::POSTMARK, ['authorization' => 'Bearer '.self::SECRET], '{}')));
        $this->assertFalse($receiver->verify(new EmailDeliveryWebhookDTO(EmailDeliveryProvider::POSTMARK, ['authorization' => 'Basic !!!'], '{}')));
        $this->assertFalse($receiver->verify(new EmailDeliveryWebhookDTO(EmailDeliveryProvider::POSTMARK, ['authorization' => 'Basic '.base64_encode(self::SECRET)], '{}')));
    }

    public function test_rejects_everything_when_no_secret_is_configured(): void
    {
        $this->assertFalse($this->receiver('')->verify($this->webhook('{}', 'any-user', '')));
    }

    /**
     * @return array<string, array{string, EmailDeliveryEventType, string}>
     */
    public static function recordTypes(): array
    {
        return [
            'delivery' => ['Delivery', EmailDeliveryEventType::DELIVERED, 'DeliveredAt'],
            'spam complaint' => ['SpamComplaint', EmailDeliveryEventType::SPAM_COMPLAINT, 'BouncedAt'],
            'open' => ['Open', EmailDeliveryEventType::OPENED, 'ReceivedAt'],
            'click' => ['Click', EmailDeliveryEventType::CLICKED, 'ReceivedAt'],
        ];
    }

    #[DataProvider('recordTypes')]
    public function test_parses_each_record_type(string $recordType, EmailDeliveryEventType $expectedType, string $timestampField): void
    {
        $events = $this->receiver()->parse($this->webhook(json_encode([
            'RecordType' => $recordType,
            'MessageID' => 'pm-message-1',
            $timestampField => '2026-10-13T09:30:00+02:00',
        ])));

        $this->assertCount(1, $events);
        $this->assertSame('pm-message-1', $events[0]->providerMessageId);
        $this->assertSame($expectedType, $events[0]->type);
        $this->assertSame('2026-10-13 07:30:00', $events[0]->occurredAt);
        $this->assertNull($events[0]->bounceType);
    }

    public function test_an_inactive_bounce_is_permanent(): void
    {
        $events = $this->receiver()->parse($this->webhook(json_encode([
            'RecordType' => 'Bounce',
            'MessageID' => 'pm-message-1',
            'Type' => 'HardBounce',
            'Inactive' => true,
            'BouncedAt' => '2026-10-13T09:30:00Z',
        ])));

        $this->assertSame(EmailDeliveryEventType::BOUNCED, $events[0]->type);
        $this->assertSame(EmailBounceType::PERMANENT, $events[0]->bounceType);
    }

    public function test_a_bounce_that_leaves_the_address_active_is_transient(): void
    {
        $events = $this->receiver()->parse($this->webhook(json_encode([
            'RecordType' => 'Bounce',
            'MessageID' => 'pm-message-1',
            'Type' => 'SoftBounce',
            'Inactive' => false,
            'BouncedAt' => '2026-10-13T09:30:00Z',
        ])));

        $this->assertSame(EmailBounceType::TRANSIENT, $events[0]->bounceType);
    }

    public function test_ignores_bounce_records_that_are_not_delivery_failures(): void
    {
        $receiver = $this->receiver();

        foreach (['AutoResponder', 'AddressChange', 'Subscribe', 'Unsubscribe', 'ChallengeVerification', 'OpenRelayTest'] as $type) {
            $this->assertSame([], $receiver->parse($this->webhook(json_encode([
                'RecordType' => 'Bounce',
                'MessageID' => 'pm-message-1',
                'Type' => $type,
                'Inactive' => false,
                'BouncedAt' => '2026-10-13T09:30:00Z',
            ]))), $type);
        }
    }

    public function test_parses_postmarks_documented_payload_and_timestamp_format(): void
    {
        $events = $this->receiver()->parse($this->webhook(json_encode([
            'RecordType' => 'Bounce',
            'MessageStream' => 'outbound',
            'ID' => 692560173,
            'Type' => 'HardBounce',
            'TypeCode' => 1,
            'Name' => 'Hard bounce',
            'Tag' => 'Test',
            'MessageID' => '883953f4-6105-42a2-a16a-77a8eac79483',
            'Metadata' => ['a_key' => 'a_value'],
            'ServerID' => 23,
            'Description' => 'The server was unable to deliver your message.',
            'Details' => 'Test bounce details',
            'Email' => 'person@example.com',
            'From' => 'sender@example.com',
            'BouncedAt' => '2026-11-05T16:33:54.9070259Z',
            'DumpAvailable' => true,
            'Inactive' => true,
            'CanActivate' => true,
            'Subject' => 'Test subject',
            'Content' => 'Dump',
        ])));

        $this->assertCount(1, $events);
        $this->assertSame('883953f4-6105-42a2-a16a-77a8eac79483', $events[0]->providerMessageId);
        $this->assertSame('2026-11-05 16:33:54', $events[0]->occurredAt);
        $this->assertSame(EmailBounceType::PERMANENT, $events[0]->bounceType);
    }

    public function test_falls_back_to_now_when_the_timestamp_is_missing_or_invalid(): void
    {
        $receiver = $this->receiver();

        $missing = $receiver->parse($this->webhook(json_encode(['RecordType' => 'Delivery', 'MessageID' => 'a'])));
        $invalid = $receiver->parse($this->webhook(json_encode(['RecordType' => 'Delivery', 'MessageID' => 'a', 'DeliveredAt' => 'not a date'])));

        $this->assertSame('2026-10-13 12:00:00', $missing[0]->occurredAt);
        $this->assertSame('2026-10-13 12:00:00', $invalid[0]->occurredAt);
    }

    public function test_ignores_unknown_record_types_missing_message_ids_and_invalid_json(): void
    {
        $receiver = $this->receiver();

        $this->assertSame([], $receiver->parse($this->webhook(json_encode(['RecordType' => 'SubscriptionChange', 'MessageID' => 'a']))));
        $this->assertSame([], $receiver->parse($this->webhook(json_encode(['RecordType' => 'Delivery']))));
        $this->assertSame([], $receiver->parse($this->webhook(json_encode(['RecordType' => 'Delivery', 'MessageID' => '']))));
        $this->assertSame([], $receiver->parse($this->webhook('not json')));
        $this->assertSame([], $receiver->parse($this->webhook('"a string"')));
    }

    private function receiver(?string $secret = self::SECRET): PostmarkEmailDeliveryWebhookReceiver
    {
        return new PostmarkEmailDeliveryWebhookReceiver(new Repository([
            'services' => ['postmark' => ['webhook_secret' => $secret]],
        ]));
    }

    private function webhook(string $payload, string $user = 'any-user', string $password = self::SECRET): EmailDeliveryWebhookDTO
    {
        return new EmailDeliveryWebhookDTO(
            provider: EmailDeliveryProvider::POSTMARK,
            headers: ['authorization' => 'Basic '.base64_encode($user.':'.$password)],
            payload: $payload,
        );
    }
}
