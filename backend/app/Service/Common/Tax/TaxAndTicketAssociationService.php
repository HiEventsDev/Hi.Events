<?php

namespace TicketKitten\Service\Common\Tax;

use Exception;
use Illuminate\Support\Collection;
use TicketKitten\Exceptions\InvalidTaxOrFeeIdException;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Service\Common\Tax\DTO\TaxAndTicketAssociateParams;

readonly class TaxAndTicketAssociationService
{
    public function __construct(
        private TaxAndFeeRepositoryInterface $taxAndFeeRepository,
        private TicketRepositoryInterface    $ticketRepository,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function addTaxesToTicket(TaxAndTicketAssociateParams $params): Collection
    {
        $taxesAndFees = $this->taxAndFeeRepository->findWhereIn(
            field: 'id',
            values: $params->taxAndFeeIds,
            additionalWhere: [
                'account_id' => $params->accountId,
                'is_active' => true,
            ],
        );

        if (count($params->taxAndFeeIds) !== $taxesAndFees->count()) {
            throw new InvalidTaxOrFeeIdException(__('One or more tax IDs are invalid'));
        }

        $this->ticketRepository->addTaxToTicket($params->ticketId, $params->taxAndFeeIds);

        return $taxesAndFees;
    }
}
