<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\EventHandlers\TerminalReaderActionFailedHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalAttemptTracker;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use Mockery;
use Mockery\MockInterface;
use Stripe\Terminal\Reader;
use Tests\TestCase;

class TerminalReaderActionFailedHandlerTest extends TestCase
{
    private MockInterface|StripePaymentsRepository $stripePaymentsRepository;

    private MockInterface|TerminalAttemptTracker $attemptTracker;

    private TerminalReaderActionFailedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepository::class);
        $this->attemptTracker = Mockery::mock(TerminalAttemptTracker::class);
        $this->handler = new TerminalReaderActionFailedHandler($this->stripePaymentsRepository, $this->attemptTracker);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_records_the_failure_on_the_stripe_payment(): void
    {
        $this->attemptTracker->shouldReceive('predatesCurrentAttempt')->with('pi_123', 1700000000)->andReturnFalse();
        $reader = Reader::constructFrom([
            'id' => 'tmr_1',
            'action' => [
                'type' => 'process_payment_intent',
                'status' => 'failed',
                'failure_code' => 'card_declined',
                'failure_message' => 'Your card was declined.',
                'process_payment_intent' => ['payment_intent' => 'pi_123'],
            ],
        ]);

        $this->stripePaymentsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [StripePaymentDomainObjectAbstract::LAST_ERROR => ['code' => 'card_declined', 'message' => 'Your card was declined.']],
                [StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => 'pi_123'],
            );

        $this->handler->handleEvent($reader, 1700000000);

        $this->assertTrue(true);
    }

    public function test_ignores_a_failure_from_a_previous_attempt(): void
    {
        $this->attemptTracker->shouldReceive('predatesCurrentAttempt')->with('pi_123', 1600000000)->andReturnTrue();
        $this->stripePaymentsRepository->shouldNotReceive('updateWhere');

        $this->handler->handleEvent(Reader::constructFrom([
            'id' => 'tmr_1',
            'action' => [
                'type' => 'process_payment_intent',
                'status' => 'failed',
                'failure_code' => 'customer_canceled',
                'process_payment_intent' => ['payment_intent' => 'pi_123'],
            ],
        ]), 1600000000);

        $this->assertTrue(true);
    }

    public function test_ignores_actions_without_a_payment_intent(): void
    {
        $reader = Reader::constructFrom(['id' => 'tmr_1', 'action' => ['type' => 'set_reader_display', 'status' => 'failed']]);
        $this->stripePaymentsRepository->shouldNotReceive('updateWhere');

        $this->handler->handleEvent($reader);

        $this->assertTrue(true);
    }
}
