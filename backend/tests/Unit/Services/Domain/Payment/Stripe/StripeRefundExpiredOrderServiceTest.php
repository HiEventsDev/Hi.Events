<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Mail\Order\PaymentSuccessButOrderExpiredMail;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\NotCompletableOrderRefundDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Contracts\Mail\Mailer;
use Mockery;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeRefundExpiredOrderServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_buyer_notification_is_sent_before_commit_so_it_survives_the_rollback(): void
    {
        $order = (new OrderDomainObject)->setCurrency('USD')
            ->setId(1)
            ->setEventId(2)
            ->setEmail('buyer@example.com')
            ->setLocale('en');

        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test');

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setSupportEmail('support@example.com');

        $organizer = new OrganizerDomainObject;

        $event = new EventDomainObject;
        $event->setId(2);
        $event->setEventSettings($eventSettings);
        $event->setOrganizer($organizer);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->with(2)->andReturn($event);

        $stripeClient = Mockery::mock(StripeClient::class);
        $clientFactory = Mockery::mock(StripeClientFactory::class);
        $clientFactory->shouldReceive('createForPlatform')->andReturn($stripeClient);

        $refundService = Mockery::mock(StripePaymentIntentRefundService::class);
        $refundService->shouldReceive('refundPayment')->once()->andReturn(Refund::constructFrom(['id' => 're_test', 'status' => 'succeeded']));

        $orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $orderRefundRepository->shouldNotReceive('create');

        $capturedMail = null;
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')->with('buyer@example.com')->andReturnSelf();
        $mailer->shouldReceive('locale')->with('en')->andReturnSelf();
        $mailer->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (PaymentSuccessButOrderExpiredMail $mail) use (&$capturedMail) {
                $capturedMail = $mail;

                return true;
            }));

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info');

        $service = new StripeRefundExpiredOrderService(
            $refundService,
            $mailer,
            $logger,
            $eventRepository,
            $clientFactory,
            $orderRefundRepository,
        );

        $refund = $service->refundExpiredOrder(
            paymentIntent: PaymentIntent::constructFrom(['id' => 'pi_test', 'amount' => 1000, 'currency' => 'usd']),
            stripePayment: $stripePayment,
            order: $order,
        );

        $this->assertSame('re_test', $refund->refundId);
        $this->assertSame(10.0, $refund->amount);
        $this->assertSame('pi_test', $refund->paymentIntentId);
        $this->assertNotNull($capturedMail);
        $this->assertFalse($capturedMail->afterCommit, 'Refund notification must dispatch beforeCommit or the rollback discards it');
    }

    public function test_recording_the_refund_writes_the_order_refund_row(): void
    {
        $written = null;
        $orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $orderRefundRepository->shouldReceive('findFirstWhere')->once()->with(['refund_id' => 're_test'])->andReturnNull();
        $orderRefundRepository->shouldReceive('create')->once()->andReturnUsing(function (array $attributes) use (&$written) {
            $written = $attributes;

            return new OrderRefundDomainObject;
        });

        $service = new StripeRefundExpiredOrderService(
            Mockery::mock(StripePaymentIntentRefundService::class),
            Mockery::mock(Mailer::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(StripeClientFactory::class),
            $orderRefundRepository,
        );

        $service->recordRefund(new NotCompletableOrderRefundDTO(
            orderId: 1,
            refundId: 're_test',
            amount: 10.0,
            currency: 'USD',
            status: 'succeeded',
            paymentIntentId: 'pi_test',
        ));

        $this->assertSame([
            'order_id' => 1,
            'payment_provider' => PaymentProviders::STRIPE->value,
            'refund_id' => 're_test',
            'amount' => 10.0,
            'currency' => 'USD',
            'status' => 'succeeded',
            'metadata' => ['payment_intent' => 'pi_test', 'reason' => 'order_not_completable'],
        ], $written);
    }

    public function test_a_refund_already_recorded_by_a_concurrent_handler_is_not_recorded_twice(): void
    {
        $orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $orderRefundRepository->shouldReceive('findFirstWhere')->once()->with(['refund_id' => 're_test'])->andReturn(new OrderRefundDomainObject);
        $orderRefundRepository->shouldNotReceive('create');

        $service = new StripeRefundExpiredOrderService(
            Mockery::mock(StripePaymentIntentRefundService::class),
            Mockery::mock(Mailer::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(StripeClientFactory::class),
            $orderRefundRepository,
        );

        $service->recordRefund(new NotCompletableOrderRefundDTO(
            orderId: 1,
            refundId: 're_test',
            amount: 10.0,
            currency: 'USD',
            status: 'succeeded',
            paymentIntentId: 'pi_test',
        ));

        $this->addToAssertionCount(1);
    }
}
