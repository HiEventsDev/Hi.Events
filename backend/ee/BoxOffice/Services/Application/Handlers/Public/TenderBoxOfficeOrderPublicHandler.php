<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\TenderBoxOfficeOrderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderCompletionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Validation\ValidationException;

class TenderBoxOfficeOrderPublicHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly BoxOfficeOrderCompletionService $completionService,
        private readonly StripeTerminalPaymentService $terminalPaymentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     * @throws ValidationException
     */
    public function handle(TenderBoxOfficeOrderDTO $data): OrderDomainObject
    {
        $order = $this->orderLookupService->findOrFail($data->box_office, $data->order_short_id);

        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($order->getEventId());

        if ($this->completionService->resolveTender($order, $data->tender) === BoxOfficeTender::COMP && ! $data->box_office->getAllowDiscounts()) {
            throw new ResourceConflictException(__('Comps are not allowed at this box office'));
        }

        $releasedPaymentIntentId = $this->terminalPaymentService->releaseCardPayment($order, $event, $data->stripe_terminal_reader_id);

        return $this->completionService->completeOffline(
            order: $order,
            event: $event,
            tender: $data->tender,
            amountTendered: $data->amount_tendered,
            reference: $data->reference,
            releasedPaymentIntentId: $releasedPaymentIntentId,
        );
    }
}
