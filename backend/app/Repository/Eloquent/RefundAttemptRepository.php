<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\RefundAttemptDomainObject;
use HiEvents\Models\RefundAttempt;
use HiEvents\Repository\Interfaces\RefundAttemptRepositoryInterface;

class RefundAttemptRepository extends BaseRepository implements RefundAttemptRepositoryInterface
{
    public function getDomainObject(): string
    {
        return RefundAttemptDomainObject::class;
    }

    protected function getModel(): string
    {
        return RefundAttempt::class;
    }

    public function findByIdempotencyKey(string $key): ?RefundAttemptDomainObject
    {
        return $this->findFirstWhere(['idempotency_key' => $key]);
    }

    public function createAttempt(string $key, int $paymentId, string $paymentType, array $requestData): RefundAttemptDomainObject
    {
        return $this->create([
            'idempotency_key' => $key,
            'payment_id' => $paymentId,
            'payment_type' => $paymentType,
            'request_data' => json_encode($requestData), // Encode to JSON string
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    public function markSucceeded(int $id, array $responseData): bool
    {
        return (bool) $this->updateFromArray($id, [
            'status' => 'succeeded',
            'response_data' => json_encode($responseData), // Encode to JSON string
        ]);
    }

    public function markFailed(int $id, array $responseData = []): bool
    {
        return (bool) $this->updateFromArray($id, [
            'status' => 'failed',
            'response_data' => json_encode($responseData), // Encode to JSON string
        ]);
    }

    public function incrementAttempts(int $id): bool
    {
        return (bool) $this->model->where('id', $id)->increment('attempts');
    }
}