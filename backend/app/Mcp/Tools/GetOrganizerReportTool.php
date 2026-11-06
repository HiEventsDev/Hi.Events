<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Reports\GetOrganizerReportAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use HiEvents\Mcp\Tools\Support\ReportPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_organizer_report')]
#[Title('Get organizer report')]
#[Description('Runs one of an organizer\'s reports across its events. revenue_summary (per day), tax_summary (per tax) and platform_fees (per paid order, in pages of 100) cover a range of days in the organizer timezone: the last 30 days unless from/to are given, at most 370 days. events_performance (per event) and check_in_summary (per event) cover all time and take no range. Money reports cover one currency, the organizer\'s default unless another is given; check_in_summary covers events in every currency. Every report except check_in_summary needs permission to view financials. Returns at most 500 rows and says when rows were left out.')]
#[IsReadOnly]
class GetOrganizerReportTool extends ApiTool
{
    public const MAX_ROWS = 500;

    public const PLATFORM_FEES_PER_PAGE = 100;

    private const UTC_COLUMNS = ['start_date', 'end_date', 'payment_date'];

    private const ALL_TIME_REPORTS = [OrganizerReportTypes::EVENTS_PERFORMANCE, OrganizerReportTypes::CHECK_IN_SUMMARY];

    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer id.')->required(),
            'report_type' => $schema->string()->enum(OrganizerReportTypes::valuesArray())->description('Which report to run.')->required(),
            'currency' => $schema->string()->description('Three-letter currency code for money reports, such as EUR. Defaults to the organizer currency.'),
            'from' => $schema->string()->description('First day to include, YYYY-MM-DD in the organizer timezone.'),
            'to' => $schema->string()->description('Last day to include, YYYY-MM-DD in the organizer timezone. Defaults to today, or to from when from is later.'),
            'page' => $schema->integer()->description('Page number for platform_fees, starting at 1.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'organizer_id' => 'required|integer|min:1',
            'report_type' => ['required', 'string', Rule::in(OrganizerReportTypes::valuesArray())],
            'currency' => 'nullable|string|alpha|size:3',
            'page' => 'nullable|integer|min:1',
            ...ReportPeriod::RULES,
        ]);

        $reportType = OrganizerReportTypes::from($validated['report_type']);
        $isAllTime = in_array($reportType, self::ALL_TIME_REPORTS, true);

        if ($isAllTime && isset($validated['from'])) {
            return Response::error(__('This report covers all time and takes no date range.'));
        }

        $organizerId = (int) $validated['organizer_id'];
        $organizer = $this->api->get(GetOrganizerAction::class, ['organizer_id' => $organizerId]);

        if (! $organizer->isSuccessful()) {
            return $this->errorResponse($organizer);
        }

        $timezone = $organizer->data()['timezone'];
        $currency = $reportType->isFinancial() ? strtoupper($validated['currency'] ?? $organizer->data()['currency']) : null;
        $period = $isAllTime ? null : ReportPeriod::fromInput($validated['from'] ?? null, $validated['to'] ?? null, $timezone);
        $isPaginated = $reportType === OrganizerReportTypes::PLATFORM_FEES;

        $response = $this->api->get(
            GetOrganizerReportAction::class,
            ['organizer_id' => $organizerId, 'report_type' => $reportType->value],
            array_filter([
                ...($period?->utcBounds() ?? []),
                'currency' => $currency,
                ...($isPaginated ? ['page' => $validated['page'] ?? 1, 'per_page' => self::PLATFORM_FEES_PER_PAGE] : []),
            ], static fn (mixed $value): bool => $value !== null),
        );

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $rows = McpPresenter::reportRows($response->data(), self::UTC_COLUMNS, $timezone, self::MAX_ROWS);
        $pagination = $response->body['pagination'] ?? null;

        return Response::structured(array_filter([
            'organizer_id' => $organizerId,
            'report_type' => $reportType->value,
            'currency' => $currency,
            'period' => $period?->toArray() ?? 'all_time',
            ...($isPaginated ? ['rows' => $rows['rows']] : $rows),
            'page' => $pagination['page'] ?? null,
            'last_page' => $pagination['last_page'] ?? null,
            'total' => $pagination['total'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
