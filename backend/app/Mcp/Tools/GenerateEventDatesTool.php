<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GenerateOccurrencesAction;
use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceGenerationStatusAction;
use HiEvents\Services\Infrastructure\Jobs\Enum\JobStatusEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Sleep;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('generate_event_dates')]
#[Title('Generate event dates from a schedule')]
#[Description('Sets a repeating schedule on an event and generates its dates, e.g. every Tuesday and Thursday at 09:00 and 13:30 until June. The event becomes RECURRING, and this replaces its previous schedule: dates without orders that no longer match are removed. Times are local to the event timezone. Waits up to 20 seconds; if generation is still running it returns a job_uuid to check later.')]
#[IsDestructive]
class GenerateEventDatesTool extends WriteApiTool
{
    public const POLL_ATTEMPTS = 10;

    public const POLL_INTERVAL_SECONDS = 2;

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'recurrence_rule' => $schema->object([
                'frequency' => $schema->string()->enum(['daily', 'weekly', 'monthly', 'yearly'])->required(),
                'interval' => $schema->integer()->description('Repeat every N periods. Defaults to 1.'),
                'days_of_week' => $schema->array()->items($schema->string()->enum(self::DAYS))->description('Required for weekly schedules.'),
                'times_of_day' => $schema->array()->items($schema->object([
                    'time' => $schema->string()->description('HH:MM, 24-hour.')->required(),
                    'label' => $schema->string()->description('Label for dates at this time, e.g. "Matinee".'),
                    'duration_minutes' => $schema->integer()->description('Length of dates at this time.'),
                ]))->description('Start times on each scheduled day. Up to 24.'),
                'duration_minutes' => $schema->integer()->description('Default length of each date.'),
                'default_capacity' => $schema->integer()->description('Capacity for each generated date.'),
                'range' => $schema->object([
                    'type' => $schema->string()->enum(['count', 'until'])->required(),
                    'count' => $schema->integer()->description('Number of dates, up to 1200, when type is count.'),
                    'until' => $schema->string()->description('Last day, YYYY-MM-DD, when type is until.'),
                    'start' => $schema->string()->description('First day, YYYY-MM-DD. Defaults to today.'),
                ])->required(),
                'excluded_dates' => $schema->array()->items($schema->string())->description('Days to skip, YYYY-MM-DD.'),
                'monthly_pattern' => $schema->string()->enum(['by_day_of_month', 'by_day_of_week'])->description('For monthly schedules.'),
                'days_of_month' => $schema->array()->items($schema->integer())->description('For by_day_of_month, e.g. [1, 15].'),
                'day_of_week' => $schema->string()->enum(self::DAYS)->description('For by_day_of_week.'),
                'week_position' => $schema->integer()->enum([-1, 1, 2, 3, 4])->description('For by_day_of_week: 1 = first, -1 = last.'),
                'month' => $schema->integer()->description('For yearly schedules, 1-12.'),
            ])->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'recurrence_rule' => 'required|array',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];

        $response = $this->api->send('POST', GenerateOccurrencesAction::class, ['event_id' => $eventId], [
            'recurrence_rule' => $validated['recurrence_rule'],
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $status = $response->body;

        for ($attempt = 0; $attempt < self::POLL_ATTEMPTS && $status['status'] === JobStatusEnum::IN_PROGRESS->name; $attempt++) {
            Sleep::for(self::POLL_INTERVAL_SECONDS)->seconds();

            $poll = $this->api->get(GetOccurrenceGenerationStatusAction::class, ['event_id' => $eventId], [
                'job_uuid' => $status['job_uuid'],
            ]);

            if (! $poll->isSuccessful()) {
                return $this->errorResponse($poll);
            }

            $status = $poll->body;
        }

        if ($status['status'] === JobStatusEnum::FAILED->name) {
            return Response::error($status['message'] ?: __('Generating the dates failed.'));
        }

        return Response::structured([
            'status' => $status['status'],
            'job_uuid' => $status['job_uuid'],
            'message' => $status['message'],
        ]);
    }
}
