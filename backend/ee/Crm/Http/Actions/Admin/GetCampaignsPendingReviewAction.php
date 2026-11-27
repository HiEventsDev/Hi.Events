<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Admin;

use HiEvents\Enterprise\Crm\Resources\AdminCampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Admin\GetCampaignsPendingReviewHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetCampaignsPendingReviewAction extends BaseAction
{
    public function __construct(
        private readonly GetCampaignsPendingReviewHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->requireSuperAdmin();

        return $this->resourceResponse(AdminCampaignResource::class, $this->handler->handle());
    }
}
