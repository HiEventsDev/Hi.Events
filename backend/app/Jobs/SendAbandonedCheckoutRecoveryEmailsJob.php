<?php

namespace HiEvents\Jobs;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\AbandonedCheckoutRecoveryMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendAbandonedCheckoutRecoveryEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        OrderRepositoryInterface $orderRepository,
        EventRepositoryInterface $eventRepository,
    ): void
    {
        // Find abandoned orders with recovery-enabled event settings that haven't
        // received the max number of recovery emails
        $abandonedOrders = DB::table('orders')
            ->join('event_settings', 'orders.event_id', '=', 'event_settings.event_id')
            ->leftJoin('abandoned_order_recoveries', 'orders.id', '=', 'abandoned_order_recoveries.order_id')
            ->where('orders.status', OrderStatus::ABANDONED->name)
            ->where('event_settings.abandoned_checkout_recovery_enabled', true)
            ->whereNotNull('orders.email')
            ->where('orders.updated_at', '<=', now()->subMinutes(
                DB::raw('event_settings.abandoned_checkout_delay_minutes')
            ))
            ->where(function ($query) {
                $query->whereNull('abandoned_order_recoveries.id')
                    ->orWhereColumn(
                        'abandoned_order_recoveries.emails_sent',
                        '<',
                        'event_settings.abandoned_checkout_max_emails'
                    );
            })
            ->where(function ($query) {
                $query->whereNull('abandoned_order_recoveries.last_email_sent_at')
                    ->orWhere('abandoned_order_recoveries.last_email_sent_at', '<=', now()->subHours(24));
            })
            ->whereNull('abandoned_order_recoveries.recovered_at')
            ->select('orders.id as order_id', 'orders.event_id', 'orders.email')
            ->limit(100)
            ->get();

        foreach ($abandonedOrders as $abandonedOrder) {
            $this->sendRecoveryEmail(
                $abandonedOrder->order_id,
                $abandonedOrder->event_id,
                $abandonedOrder->email,
                $orderRepository,
                $eventRepository,
            );
        }
    }

    private function sendRecoveryEmail(
        int                      $orderId,
        int                      $eventId,
        string                   $email,
        OrderRepositoryInterface $orderRepository,
        EventRepositoryInterface $eventRepository,
    ): void
    {
        $order = $orderRepository->findById($orderId);

        $event = $eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class))
            ->findById($eventId);

        /** @var EventSettingDomainObject $eventSettings */
        $eventSettings = $event->getEventSettings();
        /** @var OrganizerDomainObject $organizer */
        $organizer = $event->getOrganizer();

        $recoveryToken = Str::random(64);

        // Upsert recovery record
        DB::table('abandoned_order_recoveries')->updateOrInsert(
            ['order_id' => $orderId],
            [
                'event_id' => $eventId,
                'email' => $email,
                'recovery_token' => $recoveryToken,
                'emails_sent' => DB::raw('COALESCE(emails_sent, 0) + 1'),
                'last_email_sent_at' => now(),
                'cart_total' => $order->getTotalGross(),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        Mail::to($email)->send(new AbandonedCheckoutRecoveryMail(
            order: $order,
            event: $event,
            organizer: $organizer,
            eventSettings: $eventSettings,
            recoveryToken: $recoveryToken,
        ));
    }
}
