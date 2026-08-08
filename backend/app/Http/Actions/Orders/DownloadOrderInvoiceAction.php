<?php

namespace HiEvents\Http\Actions\Orders;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderInvoiceDTO;
use HiEvents\Services\Application\Handlers\Order\GetOrderInvoiceHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DownloadOrderInvoiceAction extends BaseAction
{
    public function __construct(
        private readonly GetOrderInvoiceHandler $orderInvoiceHandler,
    ) {}

    #[ResponseAttribute(status: 200, description: 'Invoice PDF', mediaType: 'application/pdf', type: 'string', format: 'binary')]
    public function __invoke(Request $request, int $eventId, int $orderId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $invoice = $this->orderInvoiceHandler->handle(new GetOrderInvoiceDTO(
            orderId: $orderId,
            eventId: $eventId,
        ));

        return $invoice->pdf->stream($invoice->filename);
    }
}
