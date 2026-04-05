<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateGiftCardAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(int $giftCardId, Request $request): JsonResponse
    {
        $this->isActionAuthorized(null, AccountDomainObject::class);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,disabled',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'personal_message' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $card = $this->giftCardRepository->updateWhere(
            attributes: $validated,
            where: [
                'id' => $giftCardId,
                'account_id' => $this->getAuthenticatedAccountId(),
            ],
        );

        return $this->jsonResponse($card->toArray());
    }
}
