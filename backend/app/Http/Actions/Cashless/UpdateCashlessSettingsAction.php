<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\UpdateCashlessSettingsRequest;
use HiEvents\Resources\Cashless\CashlessSettingsResource;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpdateCashlessSettingsDTO;
use HiEvents\Services\Application\Handlers\Cashless\UpdateCashlessSettingsHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class UpdateCashlessSettingsAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCashlessSettingsHandler $updateCashlessSettingsHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(UpdateCashlessSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $settings = $this->updateCashlessSettingsHandler->handle(UpdateCashlessSettingsDTO::from([
            'event_id' => $eventId,
            'cashless_enabled' => $request->boolean('cashless_enabled'),
            'cashless_min_topup_amount' => (float) $request->input('cashless_min_topup_amount'),
            'cashless_allow_remaining_balance_refund' => $request->boolean('cashless_allow_remaining_balance_refund'),
            'cashless_refund_deadline_at' => $request->input('cashless_refund_deadline_at'),
            'cashless_online_topup_enabled' => $request->boolean('cashless_online_topup_enabled'),
            'cashless_topup_tax_and_fee_ids' => array_map('intval', $request->input('cashless_topup_tax_and_fee_ids', [])),
        ]));

        return $this->resourceResponse(
            resource: CashlessSettingsResource::class,
            data: $settings,
        );
    }
}
