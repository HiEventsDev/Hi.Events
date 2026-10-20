<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\EmailSetting;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Mail\AccountMailerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Throwable;

class SendTestEmailAction extends BaseAction
{
    public function __construct(
        private readonly AccountMailerFactory $mailerFactory,
    ) {
    }

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        if ($accountId !== $this->getAuthenticatedAccountId()) {
            return $this->errorResponse(__('Unauthorized'));
        }

        $user = $this->getAuthenticatedUser();
        $recipientEmail = $user->getEmail();
        $recipientName = $user->getFullName();

        try {
            $mailer = $this->mailerFactory->forAccount($accountId);

            $mailer->raw(
                __('This is a test email from your Hi.Events account to verify your SMTP settings are working correctly.'),
                function (Message $message) use ($recipientEmail, $recipientName) {
                    $message->to($recipientEmail, $recipientName)
                        ->subject(__('Hi.Events — Test Email'));
                }
            );
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => __('Failed to send test email: :error', ['error' => $e->getMessage()]),
            ], 422);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => __('Test email sent to :email', ['email' => $recipientEmail]),
        ]);
    }
}
