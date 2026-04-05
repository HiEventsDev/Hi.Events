<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemGiftCardPublicAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
        ]);

        $card = $this->giftCardRepository->findByCode(strtoupper($validated['code']));

        if ($card === null) {
            throw ValidationException::withMessages([
                'code' => ['Gift card not found.'],
            ]);
        }

        if (!$card->isActive()) {
            $reason = $card->isExpired() ? 'This gift card has expired.' : 'This gift card is not active.';
            throw ValidationException::withMessages([
                'code' => [$reason],
            ]);
        }

        if ($validated['amount'] > $card->getBalance()) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance. Available: ' . $card->getBalance()],
            ]);
        }

        $result = DB::transaction(function () use ($card, $validated) {
            $newBalance = $card->getBalance() - $validated['amount'];
            $newStatus = $newBalance <= 0 ? 'depleted' : 'active';

            $this->giftCardRepository->updateWhere(
                attributes: [
                    'balance' => $newBalance,
                    'status' => $newStatus,
                ],
                where: ['id' => $card->getId()],
            );

            DB::table('gift_card_usages')->insert([
                'gift_card_id' => $card->getId(),
                'order_id' => $validated['order_id'] ?? null,
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'description' => 'Redeemed at checkout',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'redeemed' => $validated['amount'],
                'remaining_balance' => $newBalance,
                'status' => $newStatus,
            ];
        });

        return $this->jsonResponse($result);
    }
}
