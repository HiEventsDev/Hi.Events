<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\LifecycleEmails;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\LifecycleEmail\AdminLifecycleEmailResource;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\GetLifecycleEmailsHandler;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailSender;
use Illuminate\Http\JsonResponse;

class GetLifecycleEmailsAction extends BaseAction
{
    public function __construct(
        private readonly GetLifecycleEmailsHandler $handler,
        private readonly LifecycleEmailSender $sender,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->requireSuperAdmin();

        return $this->resourceResponse(
            resource: AdminLifecycleEmailResource::class,
            data: $this->handler->handle(),
            meta: ['sending_configured' => $this->sender->isConfigured()],
        );
    }
}
