<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Actions;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\Resources\LicenceResource;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetLicenceAdminAction extends BaseAction
{
    public function __construct(
        private readonly LicenceService $licenceService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        return $this->resourceResponse(
            resource: LicenceResource::class,
            data: $this->licenceService->state(),
        );
    }
}
