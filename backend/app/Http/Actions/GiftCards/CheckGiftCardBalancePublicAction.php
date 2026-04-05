<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckGiftCardBalancePublicAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $card = $this->giftCardRepository->findByCode(strtoupper($validated['code']));

        if ($card === null) {
            return $this->jsonResponse(['found' => false, 'message' => 'Gift card not found.']);
        }

        return $this->jsonResponse([
            'found' => true,
            'balance' => $card->getBalance(),
            'currency' => $card->getCurrency(),
            'status' => $card->getStatus(),
            'is_active' => $card->isActive(),
            'expires_at' => $card->getExpiresAt(),
        ]);
    }
}
