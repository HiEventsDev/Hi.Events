<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\ValidationException;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentCompletionService;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpaySignatureVerificationService;
use Throwable;

class HandlePaymentCallbackHandler
{
    public function __construct(
        private readonly RazorpaySignatureVerificationService $signatureVerificationService,
        private readonly RazorpayOrdersRepositoryInterface    $razorpayOrdersRepository,
        private readonly RazorpayPaymentCompletionService     $paymentCompletionService,
    ) {
    }

    /**
     * @throws ValidationException|ResourceNotFoundException|Throwable
     */
    public function handle(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): void
    {
        if (!$this->signatureVerificationService->verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            throw new ValidationException('Invalid Razorpay signature.');
        }

        $razorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
            RazorpayOrderDomainObjectAbstract::RAZORPAY_ORDER_ID => $razorpayOrderId,
        ]);

        if (!$razorpayOrder) {
            throw new ResourceNotFoundException('Razorpay order not found.');
        }

        $this->paymentCompletionService->completePayment(
            razorpayOrder: $razorpayOrder,
            razorpayPaymentId: $razorpayPaymentId,
            razorpaySignature: $razorpaySignature,
        );
    }
}
