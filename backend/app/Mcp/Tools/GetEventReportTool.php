<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ReportTypes;
use HiEvents\Http\Actions\Reports\GetReportAction;
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

#[Name('get_event_report')]
#[Title('Get event report')]
#[Description('Runs one of an event\'s reports. product_sales (per ticket and product), daily_sales_report (per day) and promo_codes_report (per code) cover a range of days in the event timezone: the last 30 days unless from/to are given, at most 370 days. occurrence_summary (per date of a recurring event) and seating_sales (per seating area and price band) cover all time and take no range. Every report except occurrence_summary can be limited to one date of a recurring event. Money is in the event currency. Needs permission to view financials. Returns at most 500 rows and says when rows were left out.')]
#[IsReadOnly]
class GetEventReportTool extends ApiTool
{
    public const MAX_ROWS = 500;

    private const UTC_COLUMNS = ['first_used_at', 'last_used_at', 'expiry_date', 'start_date', 'end_date'];

    private const ALL_TIME_REPORTS = [ReportTypes::OCCURRENCE_SUMMARY, ReportTypes::SEATING_SALES];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'report_type' => $schema->string()->enum(ReportTypes::valuesArray())->description('Which report to run.')->required(),
            'from' => $schema->string()->description('First day to include, YYYY-MM-DD in the event timezone.'),
            'to' => $schema->string()->description('Last day to include, YYYY-MM-DD in the event timezone. Defaults to today, or to from when from is later.'),
            'date_id' => $schema->integer()->description('Limit the report to one date of a recurring event, by its date id.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'report_type' => ['required', 'string', Rule::in(ReportTypes::valuesArray())],
            'date_id' => 'nullable|integer|min:1',
            ...ReportPeriod::RULES,
        ]);

        $reportType = ReportTypes::from($validated['report_type']);
        $isAllTime = in_array($reportType, self::ALL_TIME_REPORTS, true);
        $dateId = isset($validated['date_id']) ? (int) $validated['date_id'] : null;

        if ($isAllTime && isset($validated['from'])) {
            return Response::error(__('This report covers all time and takes no date range.'));
        }

        if ($reportType === ReportTypes::OCCURRENCE_SUMMARY && $dateId !== null) {
            return Response::error(__('occurrence_summary already lists every date, so leave out date_id.'));
        }

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $timezone = $event->data()['timezone'];
        $period = $isAllTime ? null : ReportPeriod::fromInput($validated['from'] ?? null, $validated['to'] ?? null, $timezone);

        $response = $this->api->get(
            GetReportAction::class,
            ['event_id' => $eventId, 'report_type' => $reportType->value],
            array_filter([...($period?->utcBounds() ?? []), 'occurrence_id' => $dateId], static fn (mixed $value): bool => $value !== null),
        );

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(array_filter([
            'event_id' => $eventId,
            'report_type' => $reportType->value,
            'date_id' => $dateId,
            'currency' => $event->data()['currency'],
            'period' => $period?->toArray() ?? 'all_time',
            ...McpPresenter::reportRows($response->body, self::UTC_COLUMNS, $timezone, self::MAX_ROWS),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
