<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Mail\Order\PaymentSuccessButOrderExpiredMail;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Contracts\Mail\Mailer;
use Mockery;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;
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
        $order = (new OrderDomainObject)
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
        $refundService->shouldReceive('refundPayment')->once();

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
        );

        $service->refundExpiredOrder(
            paymentIntent: PaymentIntent::constructFrom(['id' => 'pi_test', 'amount' => 1000, 'currency' => 'usd']),
            stripePayment: $stripePayment,
            order: $order,
        );

        $this->assertNotNull($capturedMail);
        $this->assertFalse($capturedMail->afterCommit, 'Refund notification must dispatch beforeCommit or the rollback discards it');
    }
}
