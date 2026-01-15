<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;

class RazorpayOrderDomainObject extends RazorpayOrderDomainObjectAbstract
{
    // Additional methods or overrides can be added here
    
    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['captured', 'paid']);
    }
    
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }
    
    public function isPending(): bool
    {
        return $this->payment_status === 'created';
    }
}