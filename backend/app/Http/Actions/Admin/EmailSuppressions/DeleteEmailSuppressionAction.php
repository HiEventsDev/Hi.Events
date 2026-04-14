<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\EmailSuppressions;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Http\Response;

class DeleteEmailSuppressionAction extends BaseAction
{
    public function __construct(
        private readonly EmailSuppressionService $emailSuppressionService,
    ) {
    }

    public function __invoke(int $suppression_id): Response
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $this->emailSuppressionService->removeSuppressionById($suppression_id);

        return $this->deletedResponse();
    }
}
