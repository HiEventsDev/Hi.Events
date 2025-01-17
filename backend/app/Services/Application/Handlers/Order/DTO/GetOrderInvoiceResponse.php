<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use Barryvdh\DomPDF\PDF;

class GetOrderInvoiceResponse
{
    public function __construct(
        public readonly PDF    $pdf,
        public readonly string $filename,
    )
    {
    }
}
