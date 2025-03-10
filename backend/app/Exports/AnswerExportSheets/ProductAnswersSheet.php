<?php

namespace HiEvents\Exports\AnswerExportSheets;

use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductAnswersSheet implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    ShouldAutoSize
{
    public function __construct(
        private readonly Collection              $answers,
        private readonly QuestionAnswerFormatter $questionAnswerFormatter,
    )
    {
    }

    public function collection(): Collection
    {
        return $this->answers;
    }

    public function headings(): array
    {
        return [
            __('Question'),
            __('Answer'),
            __('Order ID'),
            __('Order Name'),
            __('Order Email'),
            __('Product'),
            __('Order URL'),
        ];
    }

    /**
     * @param QuestionAndAnswerViewDomainObject $row
     */
    public function map($row): array
    {
        $orderUrl = sprintf(
            Url::getFrontEndUrlFromConfig(Url::ORGANIZER_ORDER_SUMMARY),
            $row->getEventId(),
            $row->getOrderId(),
        );

        $linkText = __('View Order');
        $hyperlink = '=HYPERLINK("' . $orderUrl . '","' . $linkText . '")';

        return [
            $row->getTitle(),
            $this->questionAnswerFormatter->getAnswerAsText(
                $row->getAnswer(),
                QuestionTypeEnum::fromName($row->getQuestionType())
            ),
            $row->getOrderPublicId() ?? '',
            trim($row->getOrderFirstName() . ' ' . $row->getOrderLastName()),
            $row->getOrderEmail() ?? '',
            $row->getProductTitle() ?? '',
            $hyperlink,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();

        if ($highestRow > 1) {
            $sheet->getStyle('G2:G' . $highestRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '0563C1'],
                ],
            ]);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Question
            'B' => 40, // Answer
            'C' => 15, // Order ID
            'D' => 25, // Order Name
            'E' => 25, // Order Email
            'F' => 25, // Product
            'G' => 15, // Order URL
        ];
    }

    public function title(): string
    {
        return __('Product Answers');
    }
}
