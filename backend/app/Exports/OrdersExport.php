<?php

namespace HiEvents\Exports;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private LengthAwarePaginator $orders;
    private Collection $questions;

    public function __construct(private QuestionAnswerFormatter $questionAnswerFormatter)
    {
    }

    public function withData(LengthAwarePaginator $orders, Collection $questions): OrdersExport
    {
        $this->orders = $orders;
        $this->questions = $questions;
        return $this;
    }

    public function collection(): AnonymousResourceCollection
    {
        return OrderResource::collection($this->orders);
    }

    public function headings(): array
    {
        $questionTitles = $this->questions->map(fn($question) => $question->getTitle())->toArray();

        return array_merge([
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Total Before Additions',
            'Total Gross',
            'Total Tax',
            'Total Fee',
            'Total Refunded',
            'Status',
            'Payment Status',
            'Refund Status',
            'Currency',
            'Created At',
            'Public ID',
            'Payment Gateway',
            'Is Partially Refunded',
            'Is Fully Refunded',
            'Is Free Order',
            'Is Manually Created',
        ], $questionTitles);
    }

    /**
     * @param OrderDomainObject $order
     * @return array
     */
    public function map($order): array
    {
        $answers = $this->questions->map(function (QuestionDomainObject $question) use ($order) {
            $answer = $order->getQuestionAndAnswerViews()
                ->first(fn($qav) => $qav->getQuestionId() === $question->getId())?->getAnswer() ?? '';

            return $this->questionAnswerFormatter->getAnswerAsText(
                $answer,
                QuestionTypeEnum::fromName($question->getType()),
            );
        });

        return array_merge([
            $order->getId(),
            $order->getFirstName(),
            $order->getLastName(),
            $order->getEmail(),
            $order->getTotalBeforeAdditions(),
            $order->getTotalGross(),
            $order->getTotalTax(),
            $order->getTotalFee(),
            $order->getTotalRefunded(),
            $order->getStatus(),
            $order->getPaymentStatus(),
            $order->getRefundStatus(),
            $order->getCurrency(),
            Carbon::parse($order->getCreatedAt())->format('Y-m-d H:i:s'),
            $order->getPublicId(),
            $order->getPaymentGateway(),
            $order->isPartiallyRefunded(),
            $order->isFullyRefunded(),
            $order->isFreeOrder(),
            $order->getIsManuallyCreated(),
        ], $answers->toArray());
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
