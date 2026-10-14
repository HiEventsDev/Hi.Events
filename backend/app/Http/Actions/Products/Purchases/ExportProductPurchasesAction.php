<?php

namespace HiEvents\Http\Actions\Products\Purchases;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exports\ProductPurchasesCsvExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Product\ExportProductPurchasesRequest;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Services\Application\Handlers\Product\Purchases\ExportProductPurchasesHandler;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductPurchasesAction extends BaseAction
{
    public function __construct(
        private readonly ExportProductPurchasesHandler $handler,
        private readonly ProductPurchasesCsvExport $csvExport,
    ) {}

    public function __invoke(ExportProductPurchasesRequest $request, int $eventId): StreamedResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $productId = $request->validated('product_id');
        $eventOccurrenceId = $request->validated('event_occurrence_id');

        try {
            $export = $this->handler->handle(new ProductPurchaseFilterDTO(
                eventId: $eventId,
                productId: $productId !== null ? (int) $productId : null,
                eventOccurrenceId: $eventOccurrenceId !== null ? (int) $eventOccurrenceId : null,
                statuses: $request->validated('statuses') ?? [],
                refundStatuses: $request->validated('refund_statuses') ?? [],
                query: $request->validated('query'),
            ));
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return new StreamedResponse(function () use ($export) {
            $handle = fopen('php://output', 'w');
            $this->csvExport->write($handle, $export);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="product-purchases.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
