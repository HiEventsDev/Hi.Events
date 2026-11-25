<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Mockery;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\IdempotencyException;
use Stripe\Refund;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

class FakeStripeRefunds extends RefundService
{
    public array $created = [];

    public bool $loseNextResponse = false;

    public string $statusAtStripe = 'succeeded';

    private array $responsesByIdempotencyKey = [];

    public function __construct()
    {
        parent::__construct(Mockery::mock(StripeClient::class));
    }

    public function create($params = null, $opts = null)
    {
        $key = $opts['idempotency_key'];

        if (isset($this->responsesByIdempotencyKey[$key])) {
            if ($this->responsesByIdempotencyKey[$key]['params'] !== $params) {
                throw IdempotencyException::factory('Keys for idempotent requests can only be used with the same parameters they were first used with.');
            }

            return $this->responsesByIdempotencyKey[$key]['refund'];
        }

        $refund = Refund::constructFrom([
            'id' => 're_'.(count($this->created) + 1),
            'object' => 'refund',
            'amount' => $params['amount'],
            'currency' => 'usd',
            'payment_intent' => $params['payment_intent'],
            'status' => 'pending',
            'metadata' => $params['metadata'] ?? [],
        ]);

        $this->responsesByIdempotencyKey[$key] = ['params' => $params, 'refund' => $refund];
        $this->created[] = $refund;

        if ($this->loseNextResponse) {
            $this->loseNextResponse = false;

            throw ApiConnectionException::factory('Request timed out');
        }

        return $refund;
    }

    public function retrieve($id, $params = null, $opts = null)
    {
        $refund = collect($this->created)->firstWhere('id', $id);

        return Refund::constructFrom(array_merge($refund->toArray(), ['status' => $this->statusAtStripe]));
    }
}
