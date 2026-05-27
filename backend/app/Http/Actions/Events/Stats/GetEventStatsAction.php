<?php

namespace HiEvents\Http\Actions\Events\Stats;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsRequestDTO;
use HiEvents\Services\Application\Handlers\Event\GetEventStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GetEventStatsAction extends BaseAction
{
    private const MAX_RANGE_DAYS = 370;

    public function __construct(
        private readonly GetEventStatsHandler $eventStatsHandler
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $this->validateDateRange($request);

        $occurrenceIdQuery = $request->query('occurrence_id');

        $stats = $this->eventStatsHandler->handle(EventStatsRequestDTO::fromArray([
            'event_id' => $eventId,
            'date_range_preset' => $request->query('date_range', 'month'),
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'occurrence_id' => $occurrenceIdQuery !== null ? (int)$occurrenceIdQuery : null,
        ]));

        return $this->resourceResponse(JsonResource::class, $stats);
    }

    /**
     * @return array{start_date: ?string, end_date: ?string}
     * @throws ValidationException
     */
    private function validateDateRange(Request $request): array
    {
        $validated = Validator::make(
            $request->only(['start_date', 'end_date']),
            [
                'start_date' => 'nullable|date|required_with:end_date|before_or_equal:end_date',
                'end_date' => 'nullable|date|required_with:start_date|after_or_equal:start_date',
            ],
        )->validate();

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $days = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date']));
            if ($days > self::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages([
                    'start_date' => __('Date range must be less than :days days.', ['days' => self::MAX_RANGE_DAYS]),
                ]);
            }
        }

        return [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }
}
