<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\PromoCodes\GetPromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\UpdatePromoCodeAction;
use HiEvents\Mcp\Tools\Support\PromoCodePayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_promo_code')]
#[Title('Update promo code')]
#[Description('Changes a promo code. Fields you leave out stay as they are. Set expiry_date to a past time to stop a code working.')]
#[IsDestructive]
class UpdatePromoCodeTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'promo_code_id' => $schema->integer()->description('The promo code id.')->required(),
            ...PromoCodePayload::schema($schema),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'promo_code_id' => 'required|integer|min:1',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $routeParameters = ['event_id' => $validated['event_id'], 'promo_code_id' => $validated['promo_code_id']];

        $eventResponse = $this->getEvent((int) $validated['event_id']);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $existing = $this->api->get(GetPromoCodeAction::class, $routeParameters);

        if (! $existing->isSuccessful()) {
            return $this->errorResponse($existing);
        }

        $timezone = $eventResponse->data()['timezone'];

        $response = $this->api->send('PUT', UpdatePromoCodeAction::class, $routeParameters, [
            ...PromoCodePayload::fromExisting($existing->data(), $timezone),
            ...$request->only(PromoCodePayload::FIELDS),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['promo_code' => PromoCodePayload::present($response->data(), $timezone)]);
    }
}
