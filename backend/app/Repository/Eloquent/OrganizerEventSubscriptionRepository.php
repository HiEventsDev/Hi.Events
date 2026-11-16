<?php

namespace HiEvents\Repository\Eloquent;

use Carbon\Carbon;
use Closure;
use HiEvents\DomainObjects\OrganizerEventSubscriptionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\OrganizerSubscription\FanOutNewEventNotificationsJob;
use HiEvents\Models\OrganizerEventSubscription;
use HiEvents\Services\Domain\OrganizerSubscription\DTO\SubscriberCountsData;
use HiEvents\Services\Domain\OrganizerSubscription\DTO\SubscriptionConfirmationData;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizerEventSubscriptionRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return OrganizerEventSubscription::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerEventSubscriptionDomainObject::class;
    }

    public function canSubscribeFromCheckout(int $eventId): bool
    {
        return $this->runQuery(function () use ($eventId) {
            $event = $this->db->table('events')->join('event_settings', 'event_settings.event_id', '=', 'events.id')
                ->where('events.id', $eventId)->where('events.status', 'LIVE')->whereNull('events.deleted_at')
                ->where('event_settings.show_marketing_opt_in', true)->first(['events.organizer_id']);

            return $event !== null && $this->availableOrganizers($event->organizer_id)->exists();
        });
    }

    public function isAvailable(int $organizerId): bool
    {
        return $this->runQuery(fn () => $this->availableOrganizers($organizerId)->exists());
    }

    private function availableOrganizers(int $organizerId): Builder
    {
        return $this->db->table('organizers')->join('accounts', 'accounts.id', '=', 'organizers.account_id')
            ->join('organizer_settings', 'organizer_settings.organizer_id', '=', 'organizers.id')
            ->where('organizers.id', $organizerId)->where('organizers.status', 'LIVE')
            ->whereNull('organizers.deleted_at')->whereNull('accounts.deleted_at')->whereNull('accounts.suspended_at')
            ->where('organizer_settings.new_event_notifications_enabled', true);
    }

    public function requestConfirmation(int $organizerId, string $email, string $locale, string $source, ?int $orderId = null): ?SubscriptionConfirmationData
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $email, $locale, $source, $orderId) {
            $this->db->table('organizer_settings')->where('organizer_id', $organizerId)->lockForUpdate()->first();
            if ($orderId !== null) {
                $order = $this->db->table('orders')->where('id', $orderId)->lockForUpdate()->first();
                if (! $order || $order->status !== 'COMPLETED' || ! $order->new_event_subscription_requested_at || $order->new_event_subscription_processed_at) {
                    return null;
                }
                $this->db->table('orders')->where('id', $orderId)->update(['new_event_subscription_processed_at' => now()]);
            }
            if (! $this->availableOrganizers($organizerId)->exists()) {
                return null;
            }
            $email = mb_strtolower(trim($email));
            $now = now();
            $this->db->table('organizer_event_subscriptions')->insertOrIgnore([
                'organizer_id' => $organizerId, 'email' => $email, 'locale' => $locale,
                'source' => $source, 'subscribed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $subscription = $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId)
                ->where('email', $email)->lockForUpdate()->first();
            if ($subscription->confirmed_at && ! $subscription->unsubscribed_at) {
                return null;
            }
            if ($subscription->confirmation_sent_at && Carbon::parse($subscription->confirmation_sent_at)->greaterThan(now()->subHour())) {
                return null;
            }
            $token = Str::random(64);
            $this->db->table('organizer_event_subscriptions')->where('id', $subscription->id)->update([
                'locale' => $locale, 'source' => $source, 'subscribed_at' => $now,
                'confirmed_at' => null, 'unsubscribed_at' => null, 'confirmation_token_hash' => hash('sha256', $token),
                'confirmation_expires_at' => now()->addDay(), 'confirmation_sent_at' => $now, 'updated_at' => $now,
            ]);

            return new SubscriptionConfirmationData($subscription->id, $organizerId, $email, $locale, $token);
        }));
    }

    public function confirm(int $organizerId, string $token): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $token) {
            $this->db->table('organizer_settings')->where('organizer_id', $organizerId)->lockForUpdate()->first();
            if (! $this->availableOrganizers($organizerId)->exists()) {
                throw new ResourceNotFoundException(__('Subscription is unavailable.'));
            }
            $updated = $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId)
                ->where('confirmation_token_hash', hash('sha256', $token))->whereNull('unsubscribed_at')
                ->where('confirmation_expires_at', '>', now())->update([
                    'confirmed_at' => now()->format('Y-m-d H:i:s.u'), 'confirmation_token_hash' => null,
                    'confirmation_expires_at' => null, 'updated_at' => now(),
                ]);
            if (! $updated) {
                throw new ResourceNotFoundException(__('This confirmation link is invalid or has expired. Please subscribe again.'));
            }
        }));
    }

    public function unsubscribe(int $organizerId, array $ids): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $ids) {
            $ids = array_values(array_unique($ids));
            $rows = $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId)
                ->whereIn('id', $ids)->lockForUpdate()->get();
            if ($rows->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => [__('One or more subscribers could not be found.')]]);
            }
            $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId)
                ->whereIn('id', $ids)->whereNull('unsubscribed_at')->update([
                    'unsubscribed_at' => now(), 'confirmation_token_hash' => null, 'confirmation_expires_at' => null, 'confirmation_sent_at' => null, 'updated_at' => now(),
                ]);
        }));
    }

    private function filtered(int $organizerId, ?string $search, ?string $status): Builder
    {
        $query = $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId);
        if ($search !== null && $search !== '') {
            $query->where('email', 'like', '%'.addcslashes(mb_strtolower(trim($search)), '%_'.chr(92)).'%');
        }

        return match ($status) {
            'confirmed' => $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'),
            'pending' => $query->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
            'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
            default => $query,
        };
    }

    public function subscribers(int $organizerId, ?string $search, ?string $status, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->runQuery(fn () => $this->filtered($organizerId, $search, $status)
            ->orderByDesc('subscribed_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page));
    }

    public function counts(int $organizerId): SubscriberCountsData
    {
        return $this->runQuery(fn () => new SubscriberCountsData(
            $this->filtered($organizerId, null, 'confirmed')->count(),
            $this->filtered($organizerId, null, 'pending')->count(),
            $this->filtered($organizerId, null, 'unsubscribed')->count(),
        ));
    }

    public function export(int $organizerId, ?string $search, ?string $status): iterable
    {
        return $this->runQuery(fn () => $this->filtered($organizerId, $search, $status)->orderByDesc('subscribed_at')->orderByDesc('id')->lazy());
    }

    public function recordPublication(int $eventId): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($eventId) {
            $event = $this->db->table('events')->where('id', $eventId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $event || $event->status !== 'LIVE' || $event->first_published_at !== null) {
                return;
            }
            $settings = $this->db->table('organizer_settings')->where('organizer_id', $event->organizer_id)->lockForUpdate()->first();
            $publishedAt = now()->format('Y-m-d H:i:s.u');
            $this->db->table('events')->where('id', $eventId)->update(['first_published_at' => $publishedAt]);
            if ($this->availableOrganizers($event->organizer_id)->exists()) {
                FanOutNewEventNotificationsJob::dispatch($eventId, $event->organizer_id, $publishedAt, (int) $settings->new_event_notifications_generation)->afterCommit();
            }
        }));
    }

    public function createDeliveryBatch(int $eventId, int $organizerId, string $publishedAt, int $generation, int $afterId): array
    {
        return $this->runQuery(function () use ($eventId, $organizerId, $publishedAt, $generation, $afterId) {
            if (! $this->availableOrganizers($organizerId)->where('organizer_settings.new_event_notifications_generation', $generation)->exists()) {
                return [];
            }
            $ids = $this->db->table('organizer_event_subscriptions')->where('organizer_id', $organizerId)
                ->whereNull('unsubscribed_at')->whereNotNull('confirmed_at')->where('confirmed_at', '<=', $publishedAt)
                ->where('id', '>', $afterId)->orderBy('id')->limit(200)->pluck('id')->all();
            foreach ($ids as $id) {
                $this->db->table('event_subscription_deliveries')->insertOrIgnore([
                    'event_id' => $eventId, 'subscription_id' => $id, 'generation' => $generation,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $ids;
        });
    }

    public function deliver(int $eventId, int $subscriptionId, Closure $send): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($eventId, $subscriptionId, $send) {
            $delivery = $this->db->table('event_subscription_deliveries')->where('event_id', $eventId)
                ->where('subscription_id', $subscriptionId)->lockForUpdate()->first();
            if (! $delivery || $delivery->sent_at || $delivery->skipped_at) {
                return;
            }
            $subscription = $this->db->table('organizer_event_subscriptions')->where('id', $subscriptionId)->lockForUpdate()->first();
            $event = $this->db->table('events')->where('id', $eventId)->where('organizer_id', $subscription->organizer_id)->whereNull('deleted_at')->first();
            $eligible = ! $subscription->unsubscribed_at && $subscription->confirmed_at && $event && $event->status === 'LIVE'
                && $subscription->confirmed_at <= $event->first_published_at
                && $this->availableOrganizers($subscription->organizer_id)->where('organizer_settings.new_event_notifications_generation', $delivery->generation)->exists();
            if ($eligible) {
                $send($subscription);
            }
            $this->db->table('event_subscription_deliveries')->where('id', $delivery->id)
                ->update([$eligible ? 'sent_at' : 'skipped_at' => now(), 'updated_at' => now()]);
        }));
    }
}
