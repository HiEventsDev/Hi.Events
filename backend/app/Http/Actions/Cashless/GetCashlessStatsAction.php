<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessDailyStatsResource;
use HiEvents\Services\Application\Handlers\Cashless\GetCashlessStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GetCashlessStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessStatsHandler $getCashlessStatsHandler,
    ) {}

    /**
     * Daily cashless movement for an event
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = Validator::make($request->query->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ])->validate();

        return $this->resourceResponse(
            resource: CashlessDailyStatsResource::class,
            data: $this->getCashlessStatsHandler->handle(
                eventId: $eventId,
                startDate: Carbon::parse($validated['start_date'])->toDateString(),
                endDate: Carbon::parse($validated['end_date'])->toDateString(),
            ),
        );
    }
}
