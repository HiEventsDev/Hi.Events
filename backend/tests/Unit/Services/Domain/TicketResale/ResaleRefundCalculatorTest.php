<?php

namespace Tests\Unit\Services\Domain\TicketResale;

use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundBreakdownDTO;
use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundInputDTO;
use HiEvents\Services\Domain\TicketResale\ResaleRefundCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResaleRefundCalculatorTest extends TestCase
{
    private const AMPLE_BALANCE = 1_000_000;

    private const PROPERTY_SEED = 20261125;

    private const PROPERTY_ITERATIONS = 500;

    public function test_the_seller_gets_back_what_they_paid_minus_the_replacement_sales_costs(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 5000,
            replacementOrderGross: 5000,
            replacementOrderCosts: 100 + 150,
        );

        $this->assertSame(5000, $breakdown->basis);
        $this->assertSame(250, $breakdown->cost_share);
        $this->assertSame(4750, $breakdown->refund);
    }

    public function test_the_seller_does_not_profit_when_the_price_has_risen(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 6000,
            replacementOrderGross: 6000,
            replacementOrderCosts: 300,
        );

        $this->assertSame(5000, $breakdown->basis);
        $this->assertSame(300, $breakdown->cost_share);
        $this->assertSame(4700, $breakdown->refund);
    }

    public function test_the_seller_is_refunded_no_more_than_the_replacement_paid_when_the_price_has_dropped(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 4000,
            replacementOrderGross: 4000,
            replacementOrderCosts: 200,
        );

        $this->assertSame(4000, $breakdown->basis);
        $this->assertSame(200, $breakdown->cost_share);
        $this->assertSame(3800, $breakdown->refund);
    }

    public function test_one_of_four_equally_priced_tickets_carries_a_quarter_of_the_order_costs(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 2500,
            replacementUnitGross: 2500,
            replacementOrderGross: 10000,
            replacementOrderCosts: 800,
            replacementPaidTicketCount: 4,
        );

        $this->assertSame(200, $breakdown->cost_share);
        $this->assertSame(2300, $breakdown->refund);
    }

    public function test_a_cheap_resold_ticket_in_a_mixed_order_carries_the_equal_split_not_its_share_by_price(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 1000,
            replacementUnitGross: 1000,
            replacementOrderGross: 1000 + 19000,
            replacementOrderCosts: 600,
            replacementPaidTicketCount: 2,
        );

        $this->assertSame(300, $breakdown->cost_share);
        $this->assertSame(700, $breakdown->refund);
    }

    public function test_an_expensive_resold_ticket_in_a_mixed_order_carries_its_share_by_price(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 19000,
            replacementUnitGross: 19000,
            replacementOrderGross: 1000 + 19000,
            replacementOrderCosts: 600,
            replacementPaidTicketCount: 2,
        );

        $this->assertSame(570, $breakdown->cost_share);
        $this->assertSame(18430, $breakdown->refund);
    }

    public function test_a_fractional_share_by_price_is_rounded_up(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 2000,
            replacementUnitGross: 2000,
            replacementOrderGross: 3000,
            replacementOrderCosts: 100,
            replacementPaidTicketCount: 2,
        );

        $this->assertSame(67, $breakdown->cost_share);
        $this->assertSame(1933, $breakdown->refund);
    }

    public function test_a_fractional_equal_split_is_rounded_up(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 1000,
            replacementUnitGross: 1000,
            replacementOrderGross: 9000,
            replacementOrderCosts: 100,
            replacementPaidTicketCount: 3,
        );

        $this->assertSame(34, $breakdown->cost_share);
        $this->assertSame(966, $breakdown->refund);
    }

    public function test_the_refund_is_zero_when_the_costs_exceed_the_ticket_price(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 500,
            replacementUnitGross: 500,
            replacementOrderGross: 500,
            replacementOrderCosts: 700,
        );

        $this->assertSame(500, $breakdown->basis);
        $this->assertSame(700, $breakdown->cost_share);
        $this->assertSame(0, $breakdown->refund);
    }

    public function test_the_refund_is_capped_at_what_the_sellers_order_has_left_to_refund(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 5000,
            replacementOrderGross: 5000,
            replacementOrderCosts: 250,
            sellerOrderUnrefundedBalance: 3000,
        );

        $this->assertSame(250, $breakdown->cost_share);
        $this->assertSame(3000, $breakdown->refund);
    }

    #[DataProvider('exhaustedBalanceProvider')]
    public function test_nothing_is_refunded_when_the_sellers_order_has_nothing_left_to_refund(int $balance): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 5000,
            replacementOrderGross: 5000,
            replacementOrderCosts: 250,
            sellerOrderUnrefundedBalance: $balance,
        );

        $this->assertSame(0, $breakdown->refund);
    }

    public static function exhaustedBalanceProvider(): array
    {
        return [
            'fully refunded' => [0],
            'over refunded' => [-100],
        ];
    }

    public function test_negative_costs_never_raise_the_refund_above_the_basis(): void
    {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: 5000,
            replacementOrderGross: 5000,
            replacementOrderCosts: -250,
        );

        $this->assertSame(0, $breakdown->cost_share);
        $this->assertSame(5000, $breakdown->refund);
    }

    #[DataProvider('unusableReplacementProvider')]
    public function test_nothing_is_refunded_when_the_replacement_sale_cannot_be_costed(
        int $replacementUnitGross,
        int $replacementOrderGross,
        int $replacementPaidTicketCount,
    ): void {
        $breakdown = $this->calculate(
            sellerUnitGross: 5000,
            replacementUnitGross: $replacementUnitGross,
            replacementOrderGross: $replacementOrderGross,
            replacementOrderCosts: 250,
            replacementPaidTicketCount: $replacementPaidTicketCount,
        );

        $this->assertSame(0, $breakdown->refund);
        $this->assertSame($breakdown->basis, $breakdown->cost_share);
    }

    public static function unusableReplacementProvider(): array
    {
        return [
            'zero order gross' => [5000, 0, 1],
            'negative order gross' => [5000, -5000, 1],
            'zero paid ticket count' => [5000, 5000, 0],
            'negative paid ticket count' => [5000, 5000, -1],
            'zero unit gross' => [0, 5000, 1],
            'negative unit gross' => [-5000, 5000, 1],
        ];
    }

    public function test_the_refund_never_exceeds_the_replacement_price_less_the_tickets_incremental_cost(): void
    {
        mt_srand(self::PROPERTY_SEED);

        for ($iteration = 0; $iteration < self::PROPERTY_ITERATIONS; $iteration++) {
            $sellerUnitGross = mt_rand(1, 20000);
            $replacementUnitGross = mt_rand(1, 20000);
            $otherPaidTickets = mt_rand(0, 5);
            $replacementOrderGross = $replacementUnitGross + $otherPaidTickets * mt_rand(1, 20000);
            $replacementOrderCosts = mt_rand(0, intdiv($replacementOrderGross, 4) + 50);
            $replacementPaidTicketCount = 1 + $otherPaidTickets;
            $balance = mt_rand(0, 25000);

            $breakdown = $this->calculate(
                sellerUnitGross: $sellerUnitGross,
                replacementUnitGross: $replacementUnitGross,
                replacementOrderGross: $replacementOrderGross,
                replacementOrderCosts: $replacementOrderCosts,
                replacementPaidTicketCount: $replacementPaidTicketCount,
                sellerOrderUnrefundedBalance: $balance,
            );

            $incrementalCost = max(
                $this->ceilingOfQuotient($replacementOrderCosts * $replacementUnitGross, $replacementOrderGross),
                $this->ceilingOfQuotient($replacementOrderCosts, $replacementPaidTicketCount),
            );
            $case = json_encode([
                'seller_unit_gross' => $sellerUnitGross,
                'replacement_unit_gross' => $replacementUnitGross,
                'replacement_order_gross' => $replacementOrderGross,
                'replacement_order_costs' => $replacementOrderCosts,
                'replacement_paid_ticket_count' => $replacementPaidTicketCount,
                'seller_order_unrefunded_balance' => $balance,
            ]);

            $this->assertGreaterThanOrEqual(0, $breakdown->refund, $case);
            $this->assertLessThanOrEqual(max(0, $replacementUnitGross - $incrementalCost), $breakdown->refund, $case);
            $this->assertLessThanOrEqual($sellerUnitGross, $breakdown->refund, $case);
            $this->assertLessThanOrEqual($balance, $breakdown->refund, $case);
        }
    }

    private function calculate(
        int $sellerUnitGross,
        int $replacementUnitGross,
        int $replacementOrderGross,
        int $replacementOrderCosts,
        int $replacementPaidTicketCount = 1,
        int $sellerOrderUnrefundedBalance = self::AMPLE_BALANCE,
    ): ResaleRefundBreakdownDTO {
        return (new ResaleRefundCalculator)->calculate(new ResaleRefundInputDTO(
            seller_unit_gross: $sellerUnitGross,
            replacement_unit_gross: $replacementUnitGross,
            replacement_order_gross: $replacementOrderGross,
            replacement_order_costs: $replacementOrderCosts,
            replacement_paid_ticket_count: $replacementPaidTicketCount,
            seller_order_unrefunded_balance: $sellerOrderUnrefundedBalance,
        ));
    }

    private function ceilingOfQuotient(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
