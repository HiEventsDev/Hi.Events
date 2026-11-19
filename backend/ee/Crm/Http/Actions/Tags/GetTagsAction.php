<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Tags;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\TagResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\GetTagsHandler;
use Illuminate\Http\JsonResponse;

class GetTagsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetTagsHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(TagResource::class, $this->handler->handle($organizerId));
    }
}
