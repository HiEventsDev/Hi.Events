<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Pos;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class CreateStripeTerminalConnectionTokenAction extends BaseAction
{
    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $stripeSecretKey = config('services.stripe.secret');

        if (empty($stripeSecretKey)) {
            return $this->jsonResponse(
                ['error' => 'Stripe is not configured.'],
                422
            );
        }

        $stripe = new StripeClient($stripeSecretKey);

        $connectionToken = $stripe->terminal->connectionTokens->create();

        return $this->jsonResponse([
            'secret' => $connectionToken->secret,
        ]);
    }
}
