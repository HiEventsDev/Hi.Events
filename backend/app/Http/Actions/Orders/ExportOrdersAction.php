<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Exports\OrdersExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
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

    public function __invoke(int $eventId): BinaryFileResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $orders = $this->orderRepository
            ->setMaxPerPage(10000)
            ->loadRelation(QuestionAndAnswerViewDomainObject::class)
            ->findByEventId($eventId, new QueryParamsDTO(
                page: 1,
                per_page: 10000,
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
