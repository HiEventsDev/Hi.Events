<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Resources\Account\AccountConfigurationResource;
use Illuminate\Http\JsonResponse;

class GetAllConfigurationsAction extends BaseAction
{
    public function __construct(
        private readonly AccountConfigurationRepositoryInterface $repository,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $configurations = $this->repository->all();

        return $this->jsonResponse(
            AccountConfigurationResource::collection($configurations),
            wrapInData: true
        );
    }
}
