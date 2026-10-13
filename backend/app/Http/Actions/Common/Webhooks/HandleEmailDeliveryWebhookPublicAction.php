<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\DomainObjects\Enums\EmailDeliveryProvider;
use HiEvents\Exceptions\EmailDeliveryWebhookNotConfiguredException;
use HiEvents\Exceptions\EmailDeliveryWebhookUnauthorizedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EmailDelivery\HandleEmailDeliveryWebhookHandler;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryWebhookDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HandleEmailDeliveryWebhookPublicAction extends BaseAction
{
    public function __construct(
        private readonly HandleEmailDeliveryWebhookHandler $handler,
    ) {}

    public function __invoke(Request $request, string $provider): Response
    {
        $deliveryProvider = EmailDeliveryProvider::tryFrom($provider);

        if ($deliveryProvider === null) {
            return $this->notFoundResponse();
        }

        try {
            $this->handler->handle(new EmailDeliveryWebhookDTO(
                provider: $deliveryProvider,
                headers: array_map(static fn (array $values) => (string) ($values[0] ?? ''), $request->headers->all()),
                payload: $request->getContent(),
            ));
        } catch (EmailDeliveryWebhookNotConfiguredException) {
            return $this->notFoundResponse();
        } catch (EmailDeliveryWebhookUnauthorizedException) {
            return $this->noContentResponse(ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->noContentResponse(ResponseCodes::HTTP_OK);
    }
}
