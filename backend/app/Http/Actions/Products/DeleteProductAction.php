<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Product\DeleteProductHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeleteProductAction extends BaseAction
{
    private DeleteProductHandler $deleteProductHandler;

    public function __construct(DeleteProductHandler $handler)
    {
        $this->deleteProductHandler = $handler;
    }

    public function __invoke(int $eventId, int $productId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->deleteProductHandler->handle(
                productId: $productId,
                eventId: $eventId,
            );
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->deletedResponse();
    }
}
