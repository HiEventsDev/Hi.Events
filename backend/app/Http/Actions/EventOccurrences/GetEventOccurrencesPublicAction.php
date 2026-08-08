<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use Dedoc\Scramble\Attributes\QueryParameter;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Exceptions\InvalidOccurrenceDatesException;
use HiEvents\Http\Actions\Events\BasePublicEventAction;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GetPublicEventOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetPublicEventOccurrencesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GetEventOccurrencesPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetPublicEventOccurrencesHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    #[QueryParameter('start_date_from', description: 'Only return occurrences starting on or after this date (ISO 8601).', type: 'string')]
    #[QueryParameter('start_date_to', description: 'Only return occurrences starting on or before this date (ISO 8601).', type: 'string')]
    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $startDateFrom = $request->query('start_date_from');
        $startDateTo = $request->query('start_date_to');

        try {
            $result = $this->handler->handle(new GetPublicEventOccurrencesDTO(
                eventId: $eventId,
                startDateFrom: is_string($startDateFrom) ? $startDateFrom : null,
                startDateTo: is_string($startDateTo) ? $startDateTo : null,
            ));
        } catch (InvalidOccurrenceDatesException $exception) {
            throw ValidationException::withMessages([
                'start_date_from' => $exception->getMessage(),
            ]);
        }

        if (! $this->canUserViewEvent($result->event)) {
            return $this->notFoundResponse();
        }

        $showCapacity = $result->event->getEventSettings()?->getShowAvailableOccurrenceCapacity() ?? false;

        return $this->jsonResponse([
            'data' => $result->occurrences->map(
                fn (EventOccurrenceDomainObject $occurrence) => new EventOccurrenceResourcePublic($occurrence, $showCapacity)
            )->values(),
        ]);
    }
}
