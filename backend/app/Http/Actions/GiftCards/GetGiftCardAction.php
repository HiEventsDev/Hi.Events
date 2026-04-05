<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\GiftCards;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetGiftCardAction extends BaseAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
    }

    public function __invoke(int $giftCardId, Request $request): JsonResponse
    {
        $this->isActionAuthorized(null, AccountDomainObject::class);

        $card = $this->giftCardRepository
            ->loadRelation('usages')
            ->findById($giftCardId);

        return $this->jsonResponse($card->toArray());
    }
}
