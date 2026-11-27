<?php

namespace HiEvents\Http\Actions\Events\ShareSuggestions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ShareSuggestionGenerationFailedException;
use HiEvents\Exceptions\ShareSuggestionQuotaExceededException;
use HiEvents\Exceptions\ShareSuggestionsUnavailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Event\EventShareSuggestionsResource;
use HiEvents\Services\Application\Handlers\Event\ShareSuggestion\GenerateEventShareSuggestionsHandler;
use Illuminate\Http\JsonResponse;

class GenerateEventShareSuggestionsAction extends BaseAction
{
    public function __construct(
        private readonly GenerateEventShareSuggestionsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $suggestions = $this->handler->handle($eventId, $this->getAuthenticatedAccountId());
        } catch (ShareSuggestionsUnavailableException) {
            return $this->errorResponse(
                __('Caption suggestions are not available for this event.'),
                ResponseCodes::HTTP_CONFLICT,
            );
        } catch (ShareSuggestionQuotaExceededException) {
            return $this->errorResponse(
                __('You have used all of today\'s caption suggestions. Try again tomorrow.'),
                ResponseCodes::HTTP_TOO_MANY_REQUESTS,
            );
        } catch (ShareSuggestionGenerationFailedException $exception) {
            report($exception);

            return $this->errorResponse(
                __('We couldn\'t suggest captions right now. Please try again.'),
                ResponseCodes::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->resourceResponse(EventShareSuggestionsResource::class, $suggestions);
    }
}
