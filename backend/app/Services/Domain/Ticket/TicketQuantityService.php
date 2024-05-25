<?php

namespace HiEvents\Services\Domain\Ticket;

use Illuminate\Support\Facades\DB;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;

class TicketQuantityService
{
    public function __construct(
        private readonly TicketPriceRepositoryInterface $ticketPriceRepository,
    )
    {
    }

    public function increaseTicketPriceQuantitySold(int $priceId, int $adjustment = 1): void
    {
        $this->ticketPriceRepository->updateWhere([
            'quantity_sold' => DB::raw('quantity_sold + ' . $adjustment),
        ], [
            'id' => $priceId,
        ]);
    }

    public function decreaseTicketPriceQuantitySold(int $priceId, int $adjustment = 1): void
    {
        $this->ticketPriceRepository->updateWhere([
            'quantity_sold' => DB::raw('quantity_sold - ' . $adjustment),
        ], [
            'id' => $priceId,
        ]);
    }
}
