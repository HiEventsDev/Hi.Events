<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionConfirmationReader;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetSubscriptionConfirmationPublicAction extends BaseAction
{
    public function __construct(
        private readonly SubscriptionConfirmationReader $reader,
    ) {}

    public function __invoke(string $token): JsonResponse
    {
        return $this->jsonResponse($this->reader->describe($this->reader->find($token))->toArray(), wrapInData: true);
    }
}
