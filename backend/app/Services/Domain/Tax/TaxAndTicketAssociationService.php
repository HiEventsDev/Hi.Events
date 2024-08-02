<?php

namespace HiEvents\Services\Domain\Tax;

use Exception;
use Illuminate\Support\Collection;
use HiEvents\Exceptions\InvalidTaxOrFeeIdException;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Tax\DTO\TaxAndTicketAssociateParams;

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

        $this->ticketRepository->addTaxesAndFeesToTicket($params->ticketId, $params->taxAndFeeIds);

        return $taxesAndFees;
    }
}
