<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\OrderPaymentPlatformFeeDomainObject;
use Tests\TestCase;

class OrderPaymentPlatformFeeDomainObjectTest extends TestCase
{
    public function test_the_total_includes_the_tax_stripe_charges_on_its_fee(): void
    {
        $fee = $this->fee(cardFee: 1.51, applicationFee: 0.00, rollup: ['total_fee' => 186]);

        $this->assertSame(186, $fee->getTotalFeeMinorUnit());
        $this->assertSame(186, $fee->getCardFeeMinorUnit());
    }

    public function test_the_card_fee_leaves_out_the_application_fee(): void
    {
        $fee = $this->fee(cardFee: 1.00, applicationFee: 1.50, rollup: json_encode(['total_fee' => 273]));

        $this->assertSame(273, $fee->getTotalFeeMinorUnit());
        $this->assertSame(123, $fee->getCardFeeMinorUnit());
    }

    public function test_a_row_without_stripe_totals_falls_back_to_its_stored_amounts(): void
    {
        $fee = $this->fee(cardFee: 1.00, applicationFee: 1.50, rollup: null);

        $this->assertSame(250, $fee->getTotalFeeMinorUnit());
        $this->assertSame(100, $fee->getCardFeeMinorUnit());
    }

    private function fee(float $cardFee, float $applicationFee, array|string|null $rollup): OrderPaymentPlatformFeeDomainObject
    {
        return (new OrderPaymentPlatformFeeDomainObject)
            ->setCurrency('EUR')
            ->setPaymentPlatformFeeAmount($cardFee)
            ->setApplicationFeeGrossAmount($applicationFee)
            ->setFeeRollup($rollup);
    }
}
