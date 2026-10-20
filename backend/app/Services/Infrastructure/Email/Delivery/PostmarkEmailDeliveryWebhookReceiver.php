<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Email\Delivery;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EmailBounceType;
use HiEvents\DomainObjects\Enums\EmailDeliveryEventType;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryEventDTO;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryWebhookDTO;
use Illuminate\Config\Repository;
use Throwable;

class PostmarkEmailDeliveryWebhookReceiver implements EmailDeliveryWebhookReceiverInterface
{
    private const RECORD_TYPES = [
        'Delivery' => [EmailDeliveryEventType::DELIVERED, 'DeliveredAt'],
        'Bounce' => [EmailDeliveryEventType::BOUNCED, 'BouncedAt'],
        'SpamComplaint' => [EmailDeliveryEventType::SPAM_COMPLAINT, 'BouncedAt'],
        'Open' => [EmailDeliveryEventType::OPENED, 'ReceivedAt'],
        'Click' => [EmailDeliveryEventType::CLICKED, 'ReceivedAt'],
    ];

    private const NON_FAILURE_BOUNCE_TYPES = [
        'AutoResponder',
        'AddressChange',
        'Subscribe',
        'Unsubscribe',
        'ChallengeVerification',
        'OpenRelayTest',
    ];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function isConfigured(): bool
    {
        return $this->secret() !== '';
    }

    public function verify(EmailDeliveryWebhookDTO $webhook): bool
    {
        $authorization = $webhook->headers['authorization'] ?? '';

        if (! str_starts_with($authorization, 'Basic ')) {
            return false;
        }

        $credentials = base64_decode(substr($authorization, 6), true);

        if ($credentials === false || ! str_contains($credentials, ':')) {
            return false;
        }

        [, $password] = explode(':', $credentials, 2);

        return $this->isConfigured() && hash_equals($this->secret(), $password);
    }

    public function parse(EmailDeliveryWebhookDTO $webhook): array
    {
        $record = json_decode($webhook->payload, true);

        if (! is_array($record)) {
            return [];
        }

        [$type, $timestampField] = self::RECORD_TYPES[$record['RecordType'] ?? ''] ?? [null, null];
        $messageId = $record['MessageID'] ?? null;

        if ($type === null || ! is_string($messageId) || $messageId === '') {
            return [];
        }

        if ($type === EmailDeliveryEventType::BOUNCED && in_array($record['Type'] ?? null, self::NON_FAILURE_BOUNCE_TYPES, true)) {
            return [];
        }

        return [new EmailDeliveryEventDTO(
            providerMessageId: $messageId,
            type: $type,
            occurredAt: $this->timestamp($record[$timestampField] ?? null),
            bounceType: $type === EmailDeliveryEventType::BOUNCED ? $this->bounceType($record) : null,
        )];
    }

    private function bounceType(array $record): EmailBounceType
    {
        return ($record['Inactive'] ?? false) === true
            ? EmailBounceType::PERMANENT
            : EmailBounceType::TRANSIENT;
    }

    private function timestamp(mixed $value): string
    {
        try {
            return Carbon::parse(is_string($value) ? $value : 'now')->utc()->toDateTimeString();
        } catch (Throwable) {
            return Carbon::now()->utc()->toDateTimeString();
        }
    }

    private function secret(): string
    {
        return (string) $this->config->get('services.postmark.webhook_secret');
    }
}
