<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Events\Stats\GetEventStatsAction;
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

#[Name('get_event_stats')]
#[Title('Get event sales stats')]
#[Description('Gets an event\'s totals: tickets sold, orders, attendees, page views, and gross sales, fees, tax and refunds in the event currency. Covers the whole event unless a from/to range of UTC calendar days is given (at most 370 days). Can be limited to one date of a recurring event, and can include a day-by-day breakdown. Money totals are left out for team members who may not view financials.')]
#[IsReadOnly]
class GetEventStatsTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'from' => $schema->string()->description('First day to include, YYYY-MM-DD (UTC). Leave out for the whole event.'),
            'to' => $schema->string()->description('Last day to include, YYYY-MM-DD (UTC). Defaults to today, or to from when from is later.'),
            'date_id' => $schema->integer()->description('Limit the stats to one date of a recurring event, by its date id. Page views are not tracked per date, so they are left out.'),
            'include_daily' => $schema->boolean()->description('Also return one row per day in the range, at most the latest 370 days.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'date_id' => 'nullable|integer|min:1',
            'include_daily' => 'nullable|boolean',
        ]);

        $eventId = (int) $validated['event_id'];
        $dateId = isset($validated['date_id']) ? (int) $validated['date_id'] : null;
        $from = $validated['from'] ?? null;

        $response = $this->api->get(GetEventStatsAction::class, ['event_id' => $eventId], array_filter([
            'date_range' => $from === null ? 'event' : null,
            'start_date' => $from,
            'end_date' => $from === null ? null : ($validated['to'] ?? max($from, now()->toDateString())),
            'occurrence_id' => $dateId,
        ], static fn (mixed $value): bool => $value !== null));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $data = $response->data();
        $hidden = ['daily_stats', 'start_date', 'end_date', ...($dateId === null ? [] : ['total_views'])];

        return Response::structured(array_filter([
            'event_id' => $eventId,
            'date_id' => $dateId,
            'period' => McpPresenter::statsPeriod($data),
            'stats' => Arr::except($data, $hidden),
            ...(($validated['include_daily'] ?? false) ? McpPresenter::dailyStats($data) : []),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
