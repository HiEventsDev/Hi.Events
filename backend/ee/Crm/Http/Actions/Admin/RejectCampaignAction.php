<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Admin;

use HiEvents\Enterprise\Crm\Http\Request\RejectCampaignRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Admin\ReviewCampaignHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RejectCampaignAction extends BaseAction
{
    public function __construct(
        private readonly ReviewCampaignHandler $handler,
    ) {}

    public function __invoke(RejectCampaignRequest $request, int $campaignId): JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $this->handler->reject($campaignId, $request->validated('note'));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonResponse(['message' => __('Campaign rejected')]);
    }
}
