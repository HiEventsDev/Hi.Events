<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Location\GeoPlaceDetailsHandler;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderException;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderQuotaExceededException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoPlaceDetailsAction extends BaseAction
{
    public function __construct(
        private readonly GeoPlaceDetailsHandler $handler,
    ) {}

    public function __invoke(int $organizerId, string $placeId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $locale = $request->query('locale');
        try {
            $place = $this->handler->handle(
                providerPlaceId: $placeId,
                locale: is_string($locale) ? $locale : null,
            );
        } catch (GeoProviderQuotaExceededException) {
            return $this->errorResponse(
                message: __('Place lookups are rate limited. Try again shortly or enter the address manually.'),
                statusCode: ResponseCodes::HTTP_TOO_MANY_REQUESTS,
            );
        } catch (GeoProviderException) {
            return $this->errorResponse(
                message: __('Place details are temporarily unavailable. Try again or enter the address manually.'),
                statusCode: ResponseCodes::HTTP_BAD_GATEWAY,
            );
        }

        if ($place === null) {
            return $this->errorResponse(
                message: __('Place not found or geo provider unavailable'),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        $payload = $place->toArray();
        unset($payload['raw_response']);

        return $this->jsonResponse(['data' => $payload]);
    }
}
