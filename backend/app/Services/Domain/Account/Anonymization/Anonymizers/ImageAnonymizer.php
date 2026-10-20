<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

class ImageAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $imageIds = array_column($context->imageFiles, 'id');

        if ($imageIds === []) {
            return [];
        }

        return [
            $this->executor->delete(
                query: $this->databaseManager->table('images')->whereIn('id', $imageIds),
                entity: 'images',
            ),
        ];
    }
}
