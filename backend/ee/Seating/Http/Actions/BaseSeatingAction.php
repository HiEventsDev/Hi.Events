<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Validation\ValidationException;

abstract class BaseSeatingAction extends BaseAction
{
    /**
     * @throws FeatureNotEnabledException
     */
    protected function assertSeatingEnabled(): void
    {
        app(FeatureFlagService::class)->assertEnabled(FeatureFlag::SEATING, $this->getAuthenticatedAccountId());
    }

    protected function layoutValidationException(InvalidSeatMapLayoutException $exception): ValidationException
    {
        return ValidationException::withMessages(
            collect($exception->getErrors())
                ->mapWithKeys(fn (string $message, string $path) => ["layout.$path" => $message])
                ->all(),
        );
    }
}
