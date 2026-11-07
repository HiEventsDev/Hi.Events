<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\PromoCodes\GetPromoCodesAction;
use HiEvents\Mcp\Tools\Support\PromoCodePayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_promo_codes')]
#[Title('List promo codes')]
#[Description('Lists an event\'s promo codes with their discount, tickets, expiry and how many orders have used them.')]
#[IsReadOnly]
class ListPromoCodesTool extends ApiTool
{
    public const PER_PAGE = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $response = $this->api->get(GetPromoCodesAction::class, ['event_id' => $eventId], ['per_page' => self::PER_PAGE]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $timezone = $eventResponse->data()['timezone'];

        return Response::structured([
            'promo_codes' => collect($response->data())
                ->map(static fn (array $promoCode): array => PromoCodePayload::present($promoCode, $timezone))
                ->values()
                ->all(),
        ]);
    }
}
