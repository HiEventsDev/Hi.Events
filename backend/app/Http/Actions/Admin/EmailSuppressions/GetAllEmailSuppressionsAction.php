<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\EmailSuppressions;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Resources\Admin\EmailSuppressionResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllEmailSuppressionsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllEmailSuppressionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllEmailSuppressionsAction extends BaseAction
{
    public function __construct(
        private readonly GetAllEmailSuppressionsHandler $handler,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $suppressions = $this->handler->handle(new GetAllEmailSuppressionsDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
            reason: $request->query('reason'),
            source: $request->query('source'),
            sortBy: $request->query('sort_by', 'created_at'),
            sortDirection: $request->query('sort_direction', 'desc'),
        ));

        return $this->resourceResponse(
            resource: EmailSuppressionResource::class,
            data: $suppressions,
        );
    }
}
