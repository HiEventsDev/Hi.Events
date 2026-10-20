<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\Approval;

use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;

class ApproveAccountAction extends Controller
{
    public function __construct(
        private readonly EncryptedPayloadService        $encryptedPayloadService,
        private readonly AccountRepositoryInterface     $accountRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface        $userRepository,
        private readonly EmailConfirmationService       $emailConfirmationService,
        private readonly LoggerInterface                $logger,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => __('Missing approval token.')], 400);
        }

        try {
            $payload = $this->encryptedPayloadService->decryptPayload($token);
        } catch (DecryptionFailedException|EncryptedPayloadExpiredException $e) {
            $this->logger->warning('Invalid or expired account approval token', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => __('This approval link is invalid or has expired.'),
            ], 400);
        }

        $accountId = $payload['account_id'] ?? null;
        if (!$accountId) {
            return response()->json(['message' => __('Invalid token payload.')], 400);
        }

        $account = $this->accountRepository->findById($accountId);
        if (!$account) {
            return response()->json(['message' => __('Account not found.')], 404);
        }

        if ($account->getApprovedAt() !== null) {
            return response()->json(['message' => __('This account has already been approved.')], 200);
        }

        // Approve the account
        $this->accountRepository->updateWhere(
            attributes: ['approved_at' => now()->toDateTimeString()],
            where: ['id' => $accountId],
        );

        $this->logger->info('Account approved by admin', ['account_id' => $accountId]);

        // Find the account owner and send them their confirmation email
        $accountOwner = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'is_account_owner' => true,
        ]);

        if ($accountOwner) {
            $user = $this->userRepository->findById($accountOwner->getUserId());
            $this->emailConfirmationService->sendConfirmation($user, $accountId);
        }

        return response()->json([
            'message' => __('Account approved successfully. The user has been notified.'),
        ], 200);
    }
}
