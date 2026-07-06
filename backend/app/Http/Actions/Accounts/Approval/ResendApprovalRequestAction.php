<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\Approval;

use Carbon\Carbon;
use HiEvents\Mail\Account\AccountApprovalRequestEmail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;

class ResendApprovalRequestAction extends Controller
{
    public function __construct(
        private readonly AccountRepositoryInterface     $accountRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface        $userRepository,
        private readonly EncryptedPayloadService        $encryptedPayloadService,
        private readonly Mailer                         $mailer,
        private readonly LoggerInterface                $logger,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower($validated['email']);

        // Find the user
        $user = $this->userRepository->findFirstWhere(['email' => $email]);
        if (!$user) {
            // Don't reveal whether the email exists
            return response()->json([
                'message' => __('If an account with this email exists and is pending approval, a new approval request has been sent to the administrator.'),
            ], 200);
        }

        // Find their account(s) that are pending approval
        $accountUsers = $this->accountUserRepository->findWhere([
            'user_id' => $user->getId(),
            'is_account_owner' => true,
        ]);

        $sentToAdmin = false;
        foreach ($accountUsers as $accountUser) {
            $account = $this->accountRepository->findById($accountUser->getAccountId());

            if ($account && $account->getApprovedAt() === null) {
                $this->sendApprovalEmail($user, $account);
                $sentToAdmin = true;
            }
        }

        if ($sentToAdmin) {
            $this->logger->info('Resent approval request to admin', ['email' => $email]);
        }

        return response()->json([
            'message' => __('If an account with this email exists and is pending approval, a new approval request has been sent to the administrator.'),
        ], 200);
    }

    private function sendApprovalEmail($user, $account): void
    {
        $adminEmail = config('app.admin_email');
        if (!$adminEmail) {
            $this->logger->error('APP_ADMIN_EMAIL not configured');
            return;
        }

        $token = $this->encryptedPayloadService->encryptPayload([
            'account_id' => $account->getId(),
        ], Carbon::now()->addDays(30));

        $approveUrl = config('app.frontend_url') . '/admin/approve-account?token=' . urlencode($token);

        $this->mailer
            ->to($adminEmail)
            ->send(new AccountApprovalRequestEmail($user, $account, $approveUrl));
    }
}
