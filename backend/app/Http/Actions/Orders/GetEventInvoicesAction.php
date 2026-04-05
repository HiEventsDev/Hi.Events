<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Resources\Order\Invoice\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEventInvoicesAction extends BaseAction
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $invoices = $this->invoiceRepository->findByEventId($eventId, $page, $perPage);

        return $this->paginatedResourceResponse(
            resource: InvoiceResource::class,
            data: $invoices,
        );
    }
}
