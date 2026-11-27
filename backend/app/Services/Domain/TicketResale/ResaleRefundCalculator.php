<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundBreakdownDTO;
use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundInputDTO;

class ResaleRefundCalculator
{
    public function calculate(ResaleRefundInputDTO $input): ResaleRefundBreakdownDTO
    {
        $basis = min($input->seller_unit_gross, $input->replacement_unit_gross);

        if (
            $input->replacement_order_gross <= 0
            || $input->replacement_paid_ticket_count <= 0
            || $input->replacement_unit_gross <= 0
        ) {
            return new ResaleRefundBreakdownDTO(
                basis: $basis,
                cost_share: $basis,
                refund: 0,
            );
        }

        $costShare = max(
            0,
            $this->divideRoundingUp(
                $input->replacement_order_costs * $input->replacement_unit_gross,
                $input->replacement_order_gross,
            ),
            $this->divideRoundingUp(
                $input->replacement_order_costs,
                $input->replacement_paid_ticket_count,
            ),
        );

        return new ResaleRefundBreakdownDTO(
            basis: $basis,
            cost_share: $costShare,
            refund: min(
                max(0, $basis - $costShare),
                max(0, $input->seller_order_unrefunded_balance),
            ),
        );
    }

    private function divideRoundingUp(int $dividend, int $positiveDivisor): int
    {
        return intdiv($dividend, $positiveDivisor) + ($dividend % $positiveDivisor > 0 ? 1 : 0);
    }
}
