<?php

namespace HiEvents\Http\Actions\Organizers\Stats;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Organizer\DTO\GetOrganizerStatsRequestDTO;
use HiEvents\Services\Application\Handlers\Organizer\GetOrganizerStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GetOrganizerStatsAction extends BaseAction
{
    private const MAX_RANGE_DAYS = 370;

    public function __construct(
        private readonly GetOrganizerStatsHandler $getOrganizerStatsHandler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $this->validateDateRange($request);

        $organizerStats = $this->getOrganizerStatsHandler->handle(new GetOrganizerStatsRequestDTO(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            currencyCode: $request->get('currency_code'),
            startDate: $validated['start_date'],
            endDate: $validated['end_date'],
            dateRangePreset: $request->query('date_range', 'month'),
        ));

        return $this->jsonResponse(
            data: $organizerStats,
            wrapInData: true,
        );
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
