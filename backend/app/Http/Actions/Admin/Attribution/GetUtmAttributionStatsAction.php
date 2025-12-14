<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Attribution;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUtmAttributionStatsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetUtmAttributionStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetUtmAttributionStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetUtmAttributionStatsHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $dto = GetUtmAttributionStatsDTO::from([
            'group_by' => $request->query('group_by', 'source'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'per_page' => (int) $request->query('per_page', 20),
            'page' => (int) $request->query('page', 1),
        ]);

        $result = $this->handler->handle($dto);

        return $this->jsonResponse($result);
    }
}
