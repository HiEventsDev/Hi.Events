<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalFailureResolver;
use Stripe\PaymentIntent;
use Stripe\Terminal\Reader;
use Tests\TestCase;

class TerminalFailureResolverTest extends TestCase
{
    private TerminalFailureResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TerminalFailureResolver;
    }

    public function test_no_failure_while_the_intent_is_waiting_for_a_card(): void
    {
        $intent = PaymentIntent::constructFrom(['id' => 'pi_1', 'status' => 'requires_payment_method', 'latest_charge' => null, 'last_payment_error' => null]);

        $this->assertNull($this->resolver->fromPaymentIntent($intent));
    }

    public function test_declined_charge_wins_over_last_payment_error(): void
    {
        $intent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'requires_payment_method',
            'latest_charge' => ['id' => 'ch_1', 'status' => 'failed', 'failure_code' => 'card_declined', 'failure_message' => 'Your card was declined.'],
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Insufficient funds'],
        ]);

        $this->assertSame(
            ['code' => 'card_declined', 'message' => 'Your card was declined.', 'charge_id' => 'ch_1'],
            $this->resolver->fromPaymentIntent($intent),
        );
    }

    public function test_last_payment_error_uses_the_decline_code(): void
    {
        $intent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'requires_payment_method',
            'latest_charge' => null,
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Insufficient funds', 'charge' => 'ch_2'],
        ]);

        $this->assertSame(
            ['code' => 'insufficient_funds', 'message' => 'Insufficient funds', 'charge_id' => 'ch_2'],
            $this->resolver->fromPaymentIntent($intent),
        );
    }

    public function test_a_failed_charge_from_an_earlier_attempt_is_ignored(): void
    {
        $intent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'requires_payment_method',
            'latest_charge' => ['id' => 'ch_1', 'status' => 'failed', 'failure_code' => 'card_declined', 'failure_message' => 'Declined'],
            'last_payment_error' => ['code' => 'card_declined', 'message' => 'Declined', 'charge' => 'ch_1'],
        ]);

        $this->assertNull($this->resolver->fromPaymentIntent($intent, ignoredChargeId: 'ch_1'));
        $this->assertNotNull($this->resolver->fromPaymentIntent($intent, ignoredChargeId: 'ch_0'));
        $this->assertNotNull($this->resolver->fromPaymentIntent($intent));
    }

    public function test_cancelled_intent_is_reported(): void
    {
        $intent = PaymentIntent::constructFrom(['id' => 'pi_1', 'status' => 'canceled', 'latest_charge' => null, 'last_payment_error' => null]);

        $this->assertSame(TerminalFailureResolver::CODE_PAYMENT_CANCELED, $this->resolver->fromPaymentIntent($intent)['code']);
    }

    public function test_failed_reader_action_for_this_intent_is_reported(): void
    {
        $reader = Reader::constructFrom([
            'id' => 'tmr_1',
            'status' => 'online',
            'action' => [
                'type' => 'process_payment_intent',
                'status' => 'failed',
                'failure_code' => 'customer_canceled',
                'failure_message' => 'The customer cancelled on the reader',
                'process_payment_intent' => ['payment_intent' => 'pi_1'],
            ],
        ]);

        $this->assertSame(
            ['code' => 'customer_canceled', 'message' => 'The customer cancelled on the reader', 'charge_id' => null],
            $this->resolver->fromReader($reader, 'pi_1'),
        );
        $this->assertNull($this->resolver->fromReader($reader, 'pi_other'));
    }

    public function test_offline_reader_mid_action_is_reported_and_idle_reader_is_not(): void
    {
        $offline = Reader::constructFrom([
            'id' => 'tmr_1',
            'status' => 'offline',
            'action' => ['type' => 'process_payment_intent', 'status' => 'in_progress', 'process_payment_intent' => ['payment_intent' => 'pi_1']],
        ]);
        $idle = Reader::constructFrom(['id' => 'tmr_1', 'status' => 'online', 'action' => null]);
        $busy = Reader::constructFrom([
            'id' => 'tmr_1',
            'status' => 'online',
            'action' => ['type' => 'process_payment_intent', 'status' => 'in_progress', 'process_payment_intent' => ['payment_intent' => 'pi_1']],
        ]);

        $this->assertSame(TerminalFailureResolver::CODE_READER_OFFLINE, $this->resolver->fromReader($offline, 'pi_1')['code']);
        $this->assertNull($this->resolver->fromReader($idle, 'pi_1'));
        $this->assertNull($this->resolver->fromReader($busy, 'pi_1'));
    }
}
