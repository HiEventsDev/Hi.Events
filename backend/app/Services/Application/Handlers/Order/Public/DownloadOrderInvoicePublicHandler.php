<?php

namespace HiEvents\Services\Application\Handlers\Order\Public;

use HiEvents\Services\Domain\Order\DTO\InvoicePdfResponseDTO;
use HiEvents\Services\Domain\Order\GenerateOrderInvoicePDFService;

class DownloadOrderInvoicePublicHandler
{
    public function __construct(
        private readonly GenerateOrderInvoicePDFService $generateOrderInvoicePDFService,
    )
    {
    }

    public function handle(int $eventId, string $orderShortId): InvoicePdfResponseDTO
    {
        return $this->generateOrderInvoicePDFService->generatePdfFromOrderShortId(
            orderShortId: $orderShortId,
            eventId: $eventId,
        );
    }
}
