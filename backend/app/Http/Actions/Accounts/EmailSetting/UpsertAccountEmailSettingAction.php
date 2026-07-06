<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\EmailSetting;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountEmailSettingResource;
use HiEvents\Services\Application\Handlers\Account\EmailSetting\DTO\UpsertAccountEmailSettingDTO;
use HiEvents\Services\Application\Handlers\Account\EmailSetting\UpsertAccountEmailSettingHandler;
use Illuminate\Http\JsonResponse;
use HiEvents\Rules\NotInternalHost;
use Illuminate\Http\Request;

class UpsertAccountEmailSettingAction extends BaseAction
{
    public function __construct(
        private readonly UpsertAccountEmailSettingHandler $handler,
    ) {
    }

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        if ($accountId !== $this->getAuthenticatedAccountId()) {
            return $this->errorResponse(__('Unauthorized'));
        }

        $validated = $request->validate([
            'smtp_enabled' => 'required|boolean',
            'smtp_host' => ['nullable', 'string', 'max:255', new NotInternalHost()],
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:tls,ssl,starttls',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        $setting = $this->handler->handle(new UpsertAccountEmailSettingDTO(
            accountId: $accountId,
            smtpEnabled: $validated['smtp_enabled'],
            smtpHost: $validated['smtp_host'] ?? null,
            smtpPort: isset($validated['smtp_port']) ? (int) $validated['smtp_port'] : null,
            smtpEncryption: $validated['smtp_encryption'] ?? null,
            smtpUsername: $validated['smtp_username'] ?? null,
            smtpPassword: $validated['smtp_password'] ?? null,
            mailFromAddress: $validated['mail_from_address'] ?? null,
            mailFromName: $validated['mail_from_name'] ?? null,
        ));

        return $this->resourceResponse(AccountEmailSettingResource::class, $setting);
    }
}
