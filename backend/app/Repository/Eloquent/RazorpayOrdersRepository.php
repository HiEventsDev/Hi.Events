<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Models\RazorpayOrder;
use HiEvents\Repository\Eloquent\BaseRepository;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RazorpayOrdersRepository extends BaseRepository implements RazorpayOrdersRepositoryInterface
{
    protected function getModel(): string
    {
        return RazorpayOrder::class;
    }

    public function getDomainObject(): string
    {
        return RazorpayOrderDomainObject::class;
    }

    public function findByRazorpayOrderId(string $razorpayOrderId): ?RazorpayOrderDomainObject
    {
        return $this->findFirstWhere([
            'razorpay_order_id' => $razorpayOrderId,
        ]);
    }

    public function findByOrderId(int $orderId): ?RazorpayOrderDomainObject
    {
        return $this->findFirstWhere([
            'order_id' => $orderId,
        ]);
    }

    public function updateByOrderId(int $orderId, array $data): bool
    {
        $model = $this->model
            ->where('order_id', $orderId)
            ->first();

        if (!$model) {
            return false;
        }

        return $model->update($data);
    }

    public function findByPaymentId(string $paymentId): ?RazorpayOrderDomainObject
    {
        return $this->findFirstWhere([
            'razorpay_payment_id' => $paymentId,
        ]);
    }

    protected function applySoftDeleteFilter(Builder $query): Builder
    {
        // Razorpay orders are not soft deleted
        return $query;
    }
}