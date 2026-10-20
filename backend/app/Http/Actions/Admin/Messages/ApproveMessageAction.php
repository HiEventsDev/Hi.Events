<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Messages;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\ApproveMessageHandler;
use Illuminate\Http\JsonResponse;

class ApproveMessageAction extends BaseAction
{
    public function __construct(
        private readonly ApproveMessageHandler $handler,
    ) {}

    public function __invoke(int $messageId): JsonResponse
    {
        $this->requireSuperAdmin();

        $this->handler->handle($messageId);

        return $this->jsonResponse([
            'message' => __('Message approved and queued for sending'),
        ]);
    }
}
