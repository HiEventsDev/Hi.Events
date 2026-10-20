<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class TerminalReadersResponseDTO extends BaseDataObject
{
    /**
     * @param  Collection<TerminalReaderDTO>  $readers
     */
    public function __construct(
        public Collection $readers,
        public bool $stripe_configured,
        public bool $stripe_connected,
    ) {}
}
