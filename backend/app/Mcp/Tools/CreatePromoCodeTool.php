<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\Http\Actions\PromoCodes\CreatePromoCodeAction;
use HiEvents\Mcp\Tools\Support\PromoCodePayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_promo_code')]
#[Title('Create promo code')]
#[Description('Creates a promo code for an event: a percentage or fixed discount, or no discount to unlock hidden tickets. Codes are unique per event.')]
#[IsDestructive]
class CreatePromoCodeTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        $fields = PromoCodePayload::schema($schema);
        $fields['code']->required();
        $fields['discount_type']->required();

        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            ...$fields,
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $response = $this->api->send('POST', CreatePromoCodeAction::class, ['event_id' => $eventId], [
            'discount_applies_to' => PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name,
            'applicable_product_ids' => [],
            ...$request->only(PromoCodePayload::FIELDS),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'promo_code' => PromoCodePayload::present($response->data(), $eventResponse->data()['timezone']),
        ]);
    }
}
