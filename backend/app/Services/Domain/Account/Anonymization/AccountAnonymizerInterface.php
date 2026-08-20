<?php

namespace HiEvents\Services\Domain\Account\Anonymization;

interface AccountAnonymizerInterface
{
    /**
     * @return EntityAnonymizationResult[]
     */
    public function anonymize(AnonymizationContext $context): array;
}
