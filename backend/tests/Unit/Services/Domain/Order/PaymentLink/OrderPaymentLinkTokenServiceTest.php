<?php

namespace Tests\Unit\Services\Domain\Order\PaymentLink;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Order\PaymentLink\OrderPaymentLinkTokenService;
use Illuminate\Config\Repository;
use Tests\TestCase;

class OrderPaymentLinkTokenServiceTest extends TestCase
{
    private OrderPaymentLinkTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://tickets.example.test']);
        $this->service = new OrderPaymentLinkTokenService(new Repository(['app' => ['key' => 'base64:test-key']]));
    }

    public function test_an_order_without_a_link_has_no_url_and_accepts_no_token(): void
    {
        $order = $this->order(createdAt: null);

        $this->assertNull($this->service->urlFor($order));
        $this->assertFalse($this->service->isValidToken($order, 'anything'));
        $this->assertFalse($this->service->isValidToken($order, null));
    }

    public function test_the_url_points_at_the_payment_step_and_carries_a_token_that_validates(): void
    {
        $order = $this->order();
        $url = $this->service->urlFor($order);

        $this->assertStringStartsWith('https://tickets.example.test/checkout/7/o_abc123/payment?session_identifier=', $url);
        $this->assertTrue($this->service->isValidToken($order, $this->tokenFrom($url)));
    }

    public function test_the_url_for_an_order_placed_to_pay_later_points_at_the_order_page(): void
    {
        $order = $this->order(status: OrderStatus::AWAITING_OFFLINE_PAYMENT);
        $url = $this->service->urlFor($order);

        $this->assertStringStartsWith('https://tickets.example.test/checkout/7/o_abc123/summary?session_identifier=', $url);
        $this->assertTrue($this->service->isValidToken($order, $this->tokenFrom($url)));
    }

    public function test_the_token_is_stable_across_timestamp_formats(): void
    {
        $token = $this->tokenFrom($this->service->urlFor($this->order(createdAt: '2026-10-28 10:00:00')));

        $this->assertTrue($this->service->isValidToken($this->order(createdAt: '2026-10-28T10:00:00.000000Z'), $token));
    }

    public function test_a_token_does_not_work_for_another_order_or_a_recreated_link(): void
    {
        $token = $this->tokenFrom($this->service->urlFor($this->order()));

        $this->assertFalse($this->service->isValidToken($this->order(id: 8), $token));
        $this->assertFalse($this->service->isValidToken($this->order(shortId: 'o_other'), $token));
        $this->assertFalse($this->service->isValidToken($this->order(createdAt: '2026-10-28 10:00:01'), $token));
        $this->assertFalse($this->service->isValidToken($this->order(), null));
        $this->assertFalse($this->service->isValidToken($this->order(), ''));
    }

    public function test_tokens_depend_on_the_app_key(): void
    {
        $token = $this->tokenFrom($this->service->urlFor($this->order()));
        $otherKey = new OrderPaymentLinkTokenService(new Repository(['app' => ['key' => 'base64:another-key']]));

        $this->assertFalse($otherKey->isValidToken($this->order(), $token));
    }

    private function order(
        int $id = 1,
        string $shortId = 'o_abc123',
        ?string $createdAt = '2026-10-28 10:00:00',
        OrderStatus $status = OrderStatus::RESERVED,
    ): OrderDomainObject {
        return (new OrderDomainObject)
            ->setId($id)
            ->setEventId(7)
            ->setShortId($shortId)
            ->setStatus($status->name)
            ->setPaymentLinkCreatedAt($createdAt);
    }

    private function tokenFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['session_identifier'];
    }
}
