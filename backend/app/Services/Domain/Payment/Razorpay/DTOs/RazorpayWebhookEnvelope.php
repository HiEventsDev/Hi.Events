<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use Spatie\LaravelData\Data;
use InvalidArgumentException;

class RazorpayWebhookEnvelope extends Data
{
    public function __construct(
        public readonly string $entity,
        public readonly string $account_id,
        public readonly string $event,
        public readonly RazorpayOrderPaidPayload|RazorpayPaymentPayload|RazorpayRefundPayload $payload,
        public readonly int $created_at,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $event = $data['event'];
        $payloadData = $data['payload'];

        $payload = match ($event) {
            'order.paid' => RazorpayOrderPaidPayload::from([
                'order' => $payloadData['order']['entity'],
                'payment' => $payloadData['payment']['entity'],
            ]),
            'payment.captured', 'payment.failed', 'payment.authorized' => RazorpayPaymentPayload::from([
                'payment' => $payloadData['payment']['entity'],
            ]),
            'refund.processed' => RazorpayRefundPayload::from([
                'refund' => $payloadData['refund']['entity'],
            ]),
            default => throw new InvalidArgumentException("Unknown event: {$event}"),
        };

        return new self(
            entity: $data['entity'],
            account_id: $data['account_id'],
            event: $event,
            payload: $payload,
            created_at: $data['created_at'],
        );
    }
}