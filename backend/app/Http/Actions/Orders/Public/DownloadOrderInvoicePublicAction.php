<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Public\DownloadOrderInvoicePublicHandler;
use Illuminate\Http\Response;

class DownloadOrderInvoicePublicAction extends BaseAction
{
    public function __construct(
        private readonly DownloadOrderInvoicePublicHandler $downloadOrderInvoicePublicHandler,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): Response
    {
        $invoice = $this->downloadOrderInvoicePublicHandler->handle(
            eventId: $eventId,
            orderShortId: $orderShortId,
        );

        return $invoice->pdf->stream($invoice->filename);
    }
}
