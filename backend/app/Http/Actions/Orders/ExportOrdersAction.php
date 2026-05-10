<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Exports\OrdersExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOrdersAction extends BaseAction
{
    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly OrdersExport                $export
    )
    {
    }

    public function __invoke(Request $request, int $eventId): BinaryFileResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $eventOccurrenceId = $request->input('event_occurrence_id') ? (int) $request->input('event_occurrence_id') : null;

        $filterFields = collect();
        if ($eventOccurrenceId !== null) {
            $filterFields->push(new FilterFieldDTO(
                field: 'event_occurrence_id',
                operator: 'eq',
                value: (string) $eventOccurrenceId,
            ));
        }

        $orders = $this->orderRepository
            ->setMaxPerPage(10000)
            ->loadRelation(QuestionAndAnswerViewDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        name: 'event_occurrence',
                    ),
                ],
            ))
            ->findByEventId($eventId, new QueryParamsDTO(
                page: 1,
                per_page: 10000,
                filter_fields: $filterFields->isNotEmpty() ? $filterFields : null,
            ));

        $questions = $this->questionRepository->findWhere([
            'event_id' => $eventId,
            'belongs_to' => QuestionBelongsTo::ORDER->name,
        ]);

        return Excel::download(
            $this->export->withData($orders, $questions),
            'orders.xlsx'
        );
    }
}
