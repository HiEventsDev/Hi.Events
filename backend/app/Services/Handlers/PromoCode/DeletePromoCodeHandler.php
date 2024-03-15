<?php

namespace HiEvents\Services\Handlers\PromoCode;

use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Handlers\PromoCode\DTO\DeletePromoCodeDTO;
use Psr\Log\LoggerInterface;

readonly class DeletePromoCodeHandler
{
    public function __construct(
        private PromoCodeRepositoryInterface $promoCodeRepository,
        private LoggerInterface              $logger,
    )
    {
    }

    public function handle(DeletePromoCodeDTO $data): void
    {
        $this->logger->info('Deleting promo code', [
            'promo_code_id' => $data->promo_code_id,
            'event_id' => $data->event_id,
            'user_id' => $data->user_id,
        ]);

        $this->promoCodeRepository->deleteWhere([
            'id' => $data->promo_code_id,
            'event_id' => $data->event_id,
        ]);
    }
}
