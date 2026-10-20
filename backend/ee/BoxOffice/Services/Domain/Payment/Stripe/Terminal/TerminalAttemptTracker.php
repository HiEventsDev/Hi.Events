<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use Illuminate\Contracts\Cache\Repository as Cache;
use Stripe\PaymentIntent;

class TerminalAttemptTracker
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function start(PaymentIntent $paymentIntent): void
    {
        $charge = $paymentIntent->latest_charge;
        $chargeId = is_object($charge) ? $charge->id : $charge;

        if (is_string($chargeId)) {
            $this->cache->put($this->chargeKey($paymentIntent->id), $chargeId, self::TTL_SECONDS);
        } else {
            $this->cache->forget($this->chargeKey($paymentIntent->id));
        }

        $this->cache->put($this->startedKey($paymentIntent->id), time(), self::TTL_SECONDS);
    }

    public function acknowledgedChargeId(string $paymentIntentId): ?string
    {
        $chargeId = $this->cache->get($this->chargeKey($paymentIntentId));

        return is_string($chargeId) ? $chargeId : null;
    }

    public function predatesCurrentAttempt(string $paymentIntentId, ?int $eventCreatedAt): bool
    {
        $startedAt = $this->cache->get($this->startedKey($paymentIntentId));

        return is_int($startedAt) && $eventCreatedAt !== null && $eventCreatedAt < $startedAt;
    }

    private function chargeKey(string $paymentIntentId): string
    {
        return 'box_office_card_attempt:'.$paymentIntentId;
    }

    private function startedKey(string $paymentIntentId): string
    {
        return 'box_office_card_attempt_started:'.$paymentIntentId;
    }
}
