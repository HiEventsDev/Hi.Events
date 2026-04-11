<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\EmailSuppressions;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\EmailSuppressionReasonEnum;
use HiEvents\DomainObjects\Status\EmailSuppressionSourceEnum;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Resources\Admin\EmailSuppressionResource;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateEmailSuppressionAction extends BaseAction
{
    public function __construct(
        private readonly EmailSuppressionService $emailSuppressionService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', 'in:' . implode(',', array_column(EmailSuppressionReasonEnum::cases(), 'value'))],
            'bounce_type' => ['nullable', 'string', 'max:50'],
            'complaint_type' => ['nullable', 'string', 'max:50'],
        ]);

        $suppression = $this->emailSuppressionService->suppressEmail(
            email: $validated['email'],
            reason: $validated['reason'],
            source: EmailSuppressionSourceEnum::MANUAL->value,
            bounceType: $validated['bounce_type'] ?? null,
            complaintType: $validated['complaint_type'] ?? null,
        );

        return $this->resourceResponse(
            resource: EmailSuppressionResource::class,
            data: $suppression,
            statusCode: 201,
        );
    }
}
