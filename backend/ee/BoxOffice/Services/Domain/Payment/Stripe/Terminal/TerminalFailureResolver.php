<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use Stripe\PaymentIntent;
use Stripe\Terminal\Reader;

class TerminalFailureResolver
{
    public const CODE_PAYMENT_CANCELED = 'payment_canceled';

    public const CODE_READER_OFFLINE = 'reader_offline';

    public function fromPaymentIntent(PaymentIntent $paymentIntent, ?string $ignoredChargeId = null): ?array
    {
        if ($paymentIntent->status === PaymentIntent::STATUS_CANCELED) {
            return [
                'code' => self::CODE_PAYMENT_CANCELED,
                'message' => __('The card payment was cancelled'),
                'charge_id' => null,
            ];
        }

        $charge = $paymentIntent->latest_charge;

        if (is_object($charge) && $ignoredChargeId !== null && $charge->id === $ignoredChargeId) {
            return null;
        }

        if (is_object($charge) && $charge->status === 'failed') {
            return [
                'code' => $charge->failure_code,
                'message' => $charge->failure_message,
                'charge_id' => $charge->id,
            ];
        }

        $error = $paymentIntent->last_payment_error;

        if ($error === null) {
            return null;
        }

        return [
            'code' => $error->decline_code ?? $error->code ?? null,
            'message' => $error->message ?? null,
            'charge_id' => is_object($error->charge ?? null) ? $error->charge->id : ($error->charge ?? null),
        ];
    }

    public function fromReader(Reader $reader, string $paymentIntentId): ?array
    {
        $action = $reader->action;
        $actionIntent = $action?->process_payment_intent?->payment_intent;
        $actionIntentId = is_object($actionIntent) ? $actionIntent->id : $actionIntent;

        if ($action === null || $actionIntentId !== $paymentIntentId) {
            return null;
        }

        if ($action->status === 'failed') {
            return [
                'code' => $action->failure_code,
                'message' => $action->failure_message,
                'charge_id' => null,
            ];
        }

        if ($action->status === 'in_progress' && $reader->status === 'offline') {
            return [
                'code' => self::CODE_READER_OFFLINE,
                'message' => __('The card reader went offline. Check its power and Wi-Fi.'),
                'charge_id' => null,
            ];
        }

        return null;
    }
}
