<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Resources\Organizer\OrganizerConfigurationResource;
use Illuminate\Http\JsonResponse;

class GetAllConfigurationsAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $repository,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->requireSuperAdmin();

        $configurations = $this->repository->all();

        return $this->jsonResponse(
            OrganizerConfigurationResource::collection($configurations),
            wrapInData: true
        );
    }
}
