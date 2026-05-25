<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Location\GeoAutocompleteHandler;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderException;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderQuotaExceededException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoAutocompleteAction extends BaseAction
{
    private const MAX_QUERY_LENGTH = 200;

    public function __construct(
        private readonly GeoAutocompleteHandler $handler,
    ) {}

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $query = mb_substr((string) $request->query('query', ''), 0, self::MAX_QUERY_LENGTH);
        $locale = $request->query('locale');
        $country = $request->query('country');

        try {
            $suggestions = $this->handler->handle(
                query: $query,
                locale: is_string($locale) ? $locale : null,
                country: is_string($country) ? $country : null,
            );
        } catch (GeoProviderQuotaExceededException) {
            return $this->errorResponse(
                message: __('Address suggestions are rate limited. Try again shortly or enter the address manually.'),
                statusCode: ResponseCodes::HTTP_TOO_MANY_REQUESTS,
            );
        } catch (GeoProviderException) {
            return $this->errorResponse(
                message: __('Address suggestions are temporarily unavailable. Try again or enter the address manually.'),
                statusCode: ResponseCodes::HTTP_BAD_GATEWAY,
            );
        }

        return $this->jsonResponse([
            'data' => array_map(fn ($s) => $s->toArray(), $suggestions),
        ]);
    }
}
