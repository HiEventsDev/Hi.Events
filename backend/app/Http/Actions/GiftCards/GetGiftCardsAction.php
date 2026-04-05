<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetGiftCardsAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->isActionAuthorized(null, AccountDomainObject::class);

        $cards = $this->giftCardRepository->findByAccountId(
            $this->getAuthenticatedAccountId()
        );

        return $this->jsonResponse($cards->map(fn($c) => $c->toArray())->toArray());
    }
}
