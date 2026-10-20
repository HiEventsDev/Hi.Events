<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Actions;

use HiEvents\Enterprise\Licensing\LicenceSalesService;
use HiEvents\Enterprise\Licensing\Resources\LicenceSalesPeriodResource;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetLicenceSalesAdminAction extends BaseAction
{
    public function __construct(
        private readonly LicenceSalesService $licenceSalesService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->requireSuperAdmin();

        return $this->resourceResponse(
            resource: LicenceSalesPeriodResource::class,
            data: collect($this->licenceSalesService->salesByLicenceYear()),
        );
    }
}
