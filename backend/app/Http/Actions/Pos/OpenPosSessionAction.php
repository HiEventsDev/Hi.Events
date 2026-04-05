<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Pos;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\PosSessionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenPosSessionAction extends BaseAction
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $posSessionRepository,
    ) {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'stripe_location_id' => 'nullable|string|max:255',
        ]);

        $session = $this->posSessionRepository->create([
            'event_id' => $eventId,
            'user_id' => $this->getAuthenticatedUser()->getId(),
            'name' => $validated['name'] ?? 'POS Session',
            'status' => 'active',
            'device_name' => $validated['device_name'] ?? null,
            'stripe_location_id' => $validated['stripe_location_id'] ?? null,
            'total_sales' => 0,
            'total_orders' => 0,
            'total_cash' => 0,
            'total_card' => 0,
            'opened_at' => now(),
        ]);

        return $this->jsonResponse($session->toArray(), ResponseCodes::HTTP_CREATED);
    }
}
