<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateGiftCardAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->isActionAuthorized(null, AccountDomainObject::class);

        $validated = $request->validate([
            'original_amount' => 'required|numeric|min:0.01|max:99999.99',
            'currency' => 'required|string|size:3',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'personal_message' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:now',
            'quantity' => 'integer|min:1|max:100',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $cards = [];

        for ($i = 0; $i < $quantity; $i++) {
            $card = $this->giftCardRepository->create([
                'account_id' => $this->getAuthenticatedAccountId(),
                'code' => strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4)),
                'original_amount' => $validated['original_amount'],
                'balance' => $validated['original_amount'],
                'currency' => strtoupper($validated['currency']),
                'status' => 'active',
                'purchaser_name' => $this->getAuthenticatedUser()->getFirstName() . ' ' . $this->getAuthenticatedUser()->getLastName(),
                'purchaser_email' => $this->getAuthenticatedUser()->getEmail(),
                'recipient_name' => $validated['recipient_name'] ?? null,
                'recipient_email' => $validated['recipient_email'] ?? null,
                'personal_message' => $validated['personal_message'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
            ]);
            $cards[] = $card->toArray();
        }

        return $this->jsonResponse(
            $quantity === 1 ? $cards[0] : $cards,
            ResponseCodes::HTTP_CREATED
        );
    }
}
