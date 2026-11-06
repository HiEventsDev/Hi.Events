<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Organizers\Stats\GetOrganizerStatsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_organizer_stats')]
#[Title('Get organizer sales stats')]
#[Description('Gets an organizer\'s totals across all its events: tickets sold, orders, attendees, and gross sales, fees, tax and refunds. Totals cover one currency at a time (the organizer\'s default unless another is given) and list the other currencies the organizer sells in. Covers the last 30 days unless a from/to range of UTC calendar days is given (at most 370 days). Page views are all-time and ignore the range. Money totals are left out for team members who may not view financials.')]
#[IsReadOnly]
class GetOrganizerStatsTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer id.')->required(),
            'currency' => $schema->string()->description('Three-letter currency code to total, such as EUR. Defaults to the organizer currency.'),
            'from' => $schema->string()->description('First day to include, YYYY-MM-DD (UTC). Leave out for the last 30 days.'),
            'to' => $schema->string()->description('Last day to include, YYYY-MM-DD (UTC). Defaults to today, or to from when from is later.'),
            'include_daily' => $schema->boolean()->description('Also return one row per day in the range, at most the latest 370 days.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'organizer_id' => 'required|integer|min:1',
            'currency' => 'nullable|string|alpha|size:3',
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'include_daily' => 'nullable|boolean',
        ]);

        $organizerId = (int) $validated['organizer_id'];
        $from = $validated['from'] ?? null;

        $response = $this->api->get(GetOrganizerStatsAction::class, ['organizer_id' => $organizerId], array_filter([
            'currency_code' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
            'start_date' => $from,
            'end_date' => $from === null ? null : ($validated['to'] ?? max($from, now()->toDateString())),
        ], static fn (mixed $value): bool => $value !== null));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $data = $response->data();
        $currency = $data['currency_code'];

        return Response::structured(array_filter([
            'organizer_id' => $organizerId,
            'currency' => $currency,
            'other_currencies' => array_values(array_diff($data['all_organizers_currencies'] ?? [], [$currency])),
            'period' => McpPresenter::statsPeriod($data),
            'stats' => Arr::except($data, ['daily_stats', 'start_date', 'end_date', 'currency_code', 'all_organizers_currencies', 'total_views']),
            'all_time_page_views' => $data['total_views'] ?? null,
            ...(($validated['include_daily'] ?? false) ? McpPresenter::dailyStats($data) : []),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
