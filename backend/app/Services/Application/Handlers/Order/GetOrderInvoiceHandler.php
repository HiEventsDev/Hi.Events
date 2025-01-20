<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderInvoiceDTO;
use HiEvents\Services\Domain\Order\DTO\InvoicePdfResponseDTO;
use HiEvents\Services\Domain\Order\GenerateOrderInvoicePDFService;

class GetOrderInvoiceHandler
{
    public function __construct(
        private readonly GenerateOrderInvoicePDFService $generateOrderInvoicePDFService,
    )
    {
    }

    public function handle(GetOrderInvoiceDTO $command): InvoicePdfResponseDTO
    {
        return $this->generateOrderInvoicePDFService->generatePdfFromOrderId(
            orderId: $command->orderId,
            eventId: $command->eventId,
        );
    }
}
