<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

interface RazorpayOrdersRepositoryInterface extends RepositoryInterface
{
    /**
     * Find Razorpay order by Razorpay order ID
     */
    public function findByRazorpayOrderId(string $razorpayOrderId): ?RazorpayOrderDomainObject;

    /**
     * Find Razorpay order by order ID
     */
    public function findByOrderId(int $orderId): ?RazorpayOrderDomainObject;

    /**
     * Update Razorpay order by order ID
     */
    public function updateByOrderId(int $orderId, array $data): bool;

    /**
     * Find Razorpay order by payment ID
     */
    public function findByPaymentId(string $paymentId): ?RazorpayOrderDomainObject;
}