<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\RefundAttemptDomainObject;

interface RefundAttemptRepositoryInterface extends RepositoryInterface
{
    public function findByIdempotencyKey(string $key): ?RefundAttemptDomainObject;

    public function createAttempt(string $key, int $paymentId, string $paymentType, array $requestData): RefundAttemptDomainObject;

    public function markSucceeded(int $id, array $responseData): bool;

    public function markFailed(int $id, array $responseData = []): bool;

    public function incrementAttempts(int $id): bool;
}