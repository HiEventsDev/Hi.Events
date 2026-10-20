<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers\Vat;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Organizer\Vat\OrganizerVatSettingResource;
use HiEvents\Services\Application\Handlers\Organizer\Vat\GetOrganizerVatSettingHandler;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::VIEW_FINANCIALS)]
class GetOrganizerVatSettingAction extends BaseAction
{
    public function __construct(
        private readonly GetOrganizerVatSettingHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $vatSetting = $this->handler->handle($organizerId);

        if (! $vatSetting) {
            return $this->jsonResponse(['data' => null]);
        }

        return $this->resourceResponse(OrganizerVatSettingResource::class, $vatSetting);
    }
}
