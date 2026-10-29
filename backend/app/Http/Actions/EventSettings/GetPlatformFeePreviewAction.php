<?php

namespace HiEvents\Http\Actions\EventSettings;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Event\PlatformFeePreviewResource;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\GetPlatformFeePreviewDTO;
use HiEvents\Services\Application\Handlers\EventSettings\GetPlatformFeePreviewHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[RequiresPermission(Permission::VIEW_FINANCIALS)]
class GetPlatformFeePreviewAction extends BaseAction
{
    public function __construct(
        private readonly GetPlatformFeePreviewHandler $handler,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->validate($request, [
            'price' => 'required|numeric|min:0',
        ]);

        $dto = new GetPlatformFeePreviewDTO(
            eventId: $eventId,
            price: (float) $request->input('price'),
        );

        $result = $this->handler->handle($dto);

        return $this->resourceResponse(PlatformFeePreviewResource::class, $result);
    }
}
