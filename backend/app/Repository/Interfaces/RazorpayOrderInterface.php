<?php

namespace HiEvents\DomainObjects\Interfaces;

interface RazorpayOrderInterface
{
    public function getId(): int;
    
    public function getOrderId(): int;
    
    public function getRazorpayOrderId(): string;
    
    public function getRazorpayPaymentId(): ?string;
    
    public function getRazorpaySignature(): ?string;
    
    public function getAmount(): int;
    
    public function getCurrency(): string;
    
    public function getReceipt(): ?string;
    
    public function getPaymentStatus(): string;
    
    public function getCreatedAt(): \DateTimeInterface;
    
    public function getUpdatedAt(): \DateTimeInterface;
}