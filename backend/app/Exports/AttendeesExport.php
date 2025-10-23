<?php

namespace HiEvents\Exports;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendeesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private LengthAwarePaginator|Collection $data;
    private Collection $productQuestions;
    private Collection $orderQuestions;

    public function __construct(private QuestionAnswerFormatter $questionAnswerFormatter)
    {
    }

    public function withData(LengthAwarePaginator|Collection $data, Collection $productQuestions, Collection $orderQuestions): AttendeesExport
    {
        $this->data = $data;
        $this->productQuestions = $productQuestions;
        $this->orderQuestions = $orderQuestions;
        return $this;
    }

    public function collection(): AnonymousResourceCollection
    {
        return AttendeeResource::collection($this->data);
    }

    public function headings(): array
    {
        $productQuestionTitles = $this->productQuestions->map(fn($question) => $question->getTitle())->toArray();
        $orderQuestionsTitles = $this->orderQuestions->map(fn($orderQuestion) => $orderQuestion->getTitle())->toArray();

        return array_merge([
            __('ID'),
            __('First Name'),
            __('Last Name'),
            __('Email'),
            __('Status'),
            __('Check Ins'),
            __('Product ID'),
            __('Product Name'),
            __('Event ID'),
            __('Public ID'),
            __('Short ID'),
            __('Created Date'),
            __('Last Updated Date'),
            __('Notes'),
        ], $productQuestionTitles, $orderQuestionsTitles);
    }

    /**
     * @param AttendeeDomainObject $attendee
     * @return array
     */
    public function map($attendee): array
    {
        $productAnswers = $this->productQuestions->map(function (QuestionDomainObject $question) use ($attendee) {
            $answer = $attendee->getQuestionAndAnswerViews()
                ->first(fn($qav) => $qav->getQuestionId() === $question->getId())?->getAnswer() ?? '';

            return $this->questionAnswerFormatter->getAnswerAsText(
                $answer,
                QuestionTypeEnum::fromName($question->getType()),
            );
        });

        $orderAnswers = $this->orderQuestions->map(function (QuestionDomainObject $question) use ($attendee) {
            /** @var OrderDomainObject $order */
            $order = $attendee->getOrder();
            $answer = $order->getQuestionAndAnswerViews()
                ->first(fn($qav) => $qav->getQuestionId() === $question->getId())?->getAnswer() ?? '';

            return $this->questionAnswerFormatter->getAnswerAsText(
                $answer,
                QuestionTypeEnum::fromName($question->getType()),
            );
        });

        /** @var ProductDomainObject $ticket */
        $ticket = $attendee->getProduct();
        $ticketName = $ticket->getTitle();
        if ($ticket->getType() === ProductPriceType::TIERED->name) {
            $ticketName .= ' - ' . $ticket
                    ->getProductPrices()
                    ->first(fn(ProductPriceDomainObject $tp) => $tp->getId() === $attendee->getProductPriceId())
                    ->getLabel();
        }

        $checkIns = $attendee->getCheckIns()
            ? $attendee->getCheckIns()
                ->map(fn($checkIn) => sprintf(
                    '%s (%s)',
                    $checkIn->getCheckInList()?->getName() ?? __('Unknown'),
                    Carbon::parse($checkIn->getCreatedAt())->format('Y-m-d H:i:s')
                ))
                ->join(', ')
            : '';

        return array_merge([
            $attendee->getId(),
            $attendee->getFirstName(),
            $attendee->getLastName(),
            $attendee->getEmail(),
            $attendee->getStatus(),
            $checkIns,
            $attendee->getProductId(),
            $ticketName,
            $attendee->getEventId(),
            $attendee->getPublicId(),
            $attendee->getShortId(),
            Carbon::parse($attendee->getCreatedAt())->format('Y-m-d H:i:s'),
            Carbon::parse($attendee->getUpdatedAt())->format('Y-m-d H:i:s'),
            $attendee->getNotes(),
        ], $productAnswers->toArray(), $orderAnswers->toArray());
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
