<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckDuplicateOrderPublicAction extends BaseAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $email = $request->query('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['is_duplicate' => false]);
        }

        $isDuplicate = $this->orderRepository->existsCompletedOrderForEmail(
            $eventId,
            strtolower(trim($email))
        );

        return response()->json(['is_duplicate' => $isDuplicate]);
    }
}
