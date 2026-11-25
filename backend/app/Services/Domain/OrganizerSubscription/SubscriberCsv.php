<?php

namespace HiEvents\Services\Domain\OrganizerSubscription;

class SubscriberCsv
{
    public function safeCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    public function write($stream, iterable $subscriptions): void
    {
        fputcsv($stream, [__('Email'), __('Status'), __('Signup source'), __('Subscription date'), __('Confirmation date'), __('Unsubscribe date')], escape: '');
        foreach ($subscriptions as $subscription) {
            $status = $subscription->unsubscribed_at ? __('Unsubscribed') : ($subscription->confirmed_at ? __('Confirmed') : __('Pending confirmation'));
            $source = $subscription->source === 'checkout' ? __('Checkout') : __('Public signup');
            fputcsv($stream, array_map($this->safeCell(...), [
                $subscription->email, $status, $source, $subscription->subscribed_at, $subscription->confirmed_at, $subscription->unsubscribed_at,
            ]), escape: '');
        }
    }
}
