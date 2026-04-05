<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\MfaAuditAction;
use HiEvents\Models\MfaAuditLog;
use Illuminate\Http\Request;

readonly class MfaAuditService
{
    public function log(
        int            $userId,
        MfaAuditAction $action,
        ?Request       $request = null,
        ?array         $metadata = null,
    ): void
    {
        MfaAuditLog::create([
            'user_id' => $userId,
            'action' => $action->value,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
