<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentPlatformFeeExtractionService;
use HiEvents\Services\Domain\TicketResale\ResaleRefundService;
use Mockery;
use Psr\Log\LoggerInterface;
use Stripe\Charge;
use Stripe\Exception\IdempotencyException;
use Tests\TestCase;

class ChargeSucceededHandlerTest extends TestCase
{
    public function test_a_resale_refund_that_cannot_start_does_not_fail_the_recorded_fee(): void
    {
        $order = (new OrderDomainObject)->setId(7);
        $stripePayment = (new StripePaymentDomainObject)->setId(3)->setOrder($order);

        $repository = Mockery::mock(StripePaymentsRepository::class);
        $repository->shouldReceive('loadRelation')->andReturnSelf();
        $repository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $feeExtraction = Mockery::mock(StripePaymentPlatformFeeExtractionService::class);
        $feeExtraction->shouldReceive('extractAndStorePlatformFee')->once();

        $refunds = Mockery::mock(ResaleRefundService::class);
        $refunds->shouldReceive('startRefundsForSoldOrder')->once()->with(7)
            ->andThrow(IdempotencyException::factory('Keys for idempotent requests can only be used with the same parameters they were first used with.'));

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info');
        $logger->shouldReceive('error')->once();

        (new ChargeSucceededHandler($repository, $feeExtraction, $logger, $refunds))
            ->handleEvent(Charge::constructFrom(['id' => 'ch_1', 'payment_intent' => 'pi_1', 'status' => 'succeeded']));

        $this->addToAssertionCount(1);
    }
}
