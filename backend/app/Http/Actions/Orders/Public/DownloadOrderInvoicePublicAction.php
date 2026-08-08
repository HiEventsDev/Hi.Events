<?php

namespace HiEvents\Http\Actions\Orders\Public;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Public\DownloadOrderInvoicePublicHandler;
use Illuminate\Http\Response;

class DownloadOrderInvoicePublicAction extends BaseAction
{
    public function __construct(
        private readonly DownloadOrderInvoicePublicHandler $downloadOrderInvoicePublicHandler,
    ) {}

    #[ResponseAttribute(status: 200, description: 'Invoice PDF', mediaType: 'application/pdf', type: 'string', format: 'binary')]
    public function __invoke(int $eventId, string $orderShortId): Response
    {
        $invoice = $this->downloadOrderInvoicePublicHandler->handle(
            eventId: $eventId,
            orderShortId: $orderShortId,
        );

        return $invoice->pdf->stream($invoice->filename);
    }
}
