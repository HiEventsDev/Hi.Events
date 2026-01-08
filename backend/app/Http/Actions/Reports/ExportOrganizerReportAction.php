<?php

namespace HiEvents\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Report\GetOrganizerReportRequest;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetOrganizerReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetOrganizerReportHandler;
use HiEvents\Services\Domain\Report\DTO\PaginatedReportDTO;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ExportOrganizerReportAction extends BaseAction
{
    private const MAX_EXPORT_ROWS = 15000;

    public function __construct(private readonly GetOrganizerReportHandler $reportHandler)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(GetOrganizerReportRequest $request, int $organizerId, string $reportType): StreamedResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->validateDateRange($request);

        if (!in_array($reportType, OrganizerReportTypes::valuesArray(), true)) {
            throw new BadRequestHttpException(__('Invalid report type.'));
        }

        $reportData = $this->reportHandler->handle(
            reportData: new GetOrganizerReportDTO(
                organizerId: $organizerId,
                reportType: OrganizerReportTypes::from($reportType),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
                currency: $request->validated('currency'),
                eventId: $request->validated('event_id'),
                page: 1,
                perPage: self::MAX_EXPORT_ROWS,
            ),
        );

        $data = $reportData instanceof PaginatedReportDTO
            ? $reportData->data
            : $reportData;

        $filename = $reportType . '_' . date('Y-m-d_H-i-s') . '.csv';

        return new StreamedResponse(function () use ($data, $reportType) {
            $handle = fopen('php://output', 'w');

            $headers = $this->getHeadersForReportType($reportType);
            fputcsv($handle, $headers);

            foreach ($data as $row) {
                $csvRow = $this->formatRowForReportType($row, $reportType);
                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function getHeadersForReportType(string $reportType): array
    {
        return match ($reportType) {
            OrganizerReportTypes::PLATFORM_FEES->value => [
                'Event',
                'Payment Date',
                'Order Reference',
                'Amount Paid',
                'Hi.Events Fee',
                'VAT Rate',
                'VAT on Fee',
                'Total Fee',
                'Currency',
                'Stripe Payment ID',
            ],
            OrganizerReportTypes::REVENUE_SUMMARY->value => [
                'Date',
                'Gross Sales',
                'Net Revenue',
                'Total Refunded',
                'Total Tax',
                'Total Fee',
                'Order Count',
            ],
            OrganizerReportTypes::EVENTS_PERFORMANCE->value => [
                'Event ID',
                'Event Name',
                'Currency',
                'Start Date',
                'End Date',
                'Status',
                'Event State',
                'Products Sold',
                'Gross Revenue',
                'Total Refunded',
                'Net Revenue',
                'Total Tax',
                'Total Fee',
                'Total Orders',
                'Unique Customers',
                'Page Views',
            ],
            OrganizerReportTypes::TAX_SUMMARY->value => [
                'Event ID',
                'Event Name',
                'Currency',
                'Tax Name',
                'Tax Rate',
                'Total Collected',
                'Order Count',
            ],
            OrganizerReportTypes::CHECK_IN_SUMMARY->value => [
                'Event ID',
                'Event Name',
                'Start Date',
                'Total Attendees',
                'Total Checked In',
                'Check-in Rate (%)',
                'Check-in Lists Count',
            ],
            default => [],
        };
    }

    private function formatRowForReportType(object $row, string $reportType): array
    {
        return match ($reportType) {
            OrganizerReportTypes::PLATFORM_FEES->value => [
                $row->event_name ?? '',
                $row->payment_date ? date('Y-m-d H:i:s', strtotime($row->payment_date)) : '',
                $row->order_reference ?? '',
                $row->amount_paid ?? 0,
                $row->fee_amount ?? 0,
                $row->vat_rate !== null ? ($row->vat_rate * 100) . '%' : '',
                $row->vat_amount ?? 0,
                $row->total_fee ?? 0,
                $row->currency ?? '',
                $row->payment_intent_id ?? '',
            ],
            OrganizerReportTypes::REVENUE_SUMMARY->value => [
                $row->date ?? '',
                $row->gross_sales ?? 0,
                $row->net_revenue ?? 0,
                $row->total_refunded ?? 0,
                $row->total_tax ?? 0,
                $row->total_fee ?? 0,
                $row->order_count ?? 0,
            ],
            OrganizerReportTypes::EVENTS_PERFORMANCE->value => [
                $row->event_id ?? '',
                $row->event_name ?? '',
                $row->event_currency ?? '',
                $row->start_date ?? '',
                $row->end_date ?? '',
                $row->status ?? '',
                $row->event_state ?? '',
                $row->products_sold ?? 0,
                $row->gross_revenue ?? 0,
                $row->total_refunded ?? 0,
                $row->net_revenue ?? 0,
                $row->total_tax ?? 0,
                $row->total_fee ?? 0,
                $row->total_orders ?? 0,
                $row->unique_customers ?? 0,
                $row->page_views ?? 0,
            ],
            OrganizerReportTypes::TAX_SUMMARY->value => [
                $row->event_id ?? '',
                $row->event_name ?? '',
                $row->event_currency ?? '',
                $row->tax_name ?? '',
                $row->tax_rate ? ($row->tax_rate * 100) . '%' : '',
                $row->total_collected ?? 0,
                $row->order_count ?? 0,
            ],
            OrganizerReportTypes::CHECK_IN_SUMMARY->value => [
                $row->event_id ?? '',
                $row->event_name ?? '',
                $row->start_date ?? '',
                $row->total_attendees ?? 0,
                $row->total_checked_in ?? 0,
                $row->check_in_rate ?? 0,
                $row->check_in_lists_count ?? 0,
            ],
            default => [],
        };
    }

    /**
     * @throws ValidationException
     */
    private function validateDateRange(GetOrganizerReportRequest $request): void
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        if (!$startDate || !$endDate) {
            return;
        }

        $diffInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));

        if ($diffInDays > 370) {
            throw ValidationException::withMessages(['start_date' => __('Date range must be less than 370 days.')]);
        }
    }
}
