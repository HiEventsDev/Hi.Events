<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Attribution;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\GetUtmAttributionStatsRequest;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUtmAttributionStatsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetUtmAttributionStatsHandler;
use Illuminate\Http\JsonResponse;

class GetUtmAttributionStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetUtmAttributionStatsHandler $handler,
    ) {}

    public function __invoke(GetUtmAttributionStatsRequest $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $dto = GetUtmAttributionStatsDTO::from([
            'group_by' => $request->validated('group_by') ?? 'source',
            'date_from' => $request->validated('date_from'),
            'date_to' => $request->validated('date_to'),
            'per_page' => (int) ($request->validated('per_page') ?? 20),
            'page' => (int) ($request->validated('page') ?? 1),
        ]);

        $result = $this->handler->handle($dto);

        return $this->jsonResponse($result);
    }
}
