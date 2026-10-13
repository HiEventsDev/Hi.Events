<?php

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\EmailDeliveryDomainObject;
use HiEvents\DomainObjects\Enums\EmailBounceType;
use HiEvents\DomainObjects\Enums\EmailDeliveryEventType;
use HiEvents\DomainObjects\Generated\EmailDeliveryDomainObjectAbstract;
use HiEvents\Models\EmailDelivery;
use HiEvents\Repository\DTO\EmailDelivery\EmailDeliveryCategoryStatsDTO;
use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryEventDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<EmailDeliveryDomainObject>
 */
class EmailDeliveryRepository extends BaseRepository implements EmailDeliveryRepositoryInterface
{
    protected function getModel(): string
    {
        return EmailDelivery::class;
    }

    public function getDomainObject(): string
    {
        return EmailDeliveryDomainObject::class;
    }

    public function recordEvent(EmailDeliveryEventDTO $event): void
    {
        $this->runQuery(function () use ($event) {
            $query = $this->model->where(EmailDeliveryDomainObjectAbstract::PROVIDER_MESSAGE_ID, $event->providerMessageId);

            if ($event->type === EmailDeliveryEventType::BOUNCED) {
                $this->recordBounce($query, $event);

                return;
            }

            $column = match ($event->type) {
                EmailDeliveryEventType::DELIVERED => EmailDeliveryDomainObjectAbstract::DELIVERED_AT,
                EmailDeliveryEventType::SPAM_COMPLAINT => EmailDeliveryDomainObjectAbstract::COMPLAINED_AT,
                EmailDeliveryEventType::OPENED => EmailDeliveryDomainObjectAbstract::FIRST_OPENED_AT,
                EmailDeliveryEventType::CLICKED => EmailDeliveryDomainObjectAbstract::FIRST_CLICKED_AT,
            };

            $query->whereNull($column)->update([$column => $event->occurredAt]);
        });
    }

    public function getCategoryStats(CarbonInterface $since): Collection
    {
        return $this->runQuery(fn () => $this->model
            ->toBase()
            ->where(EmailDeliveryDomainObjectAbstract::CREATED_AT, '>=', $since->toDateTimeString())
            ->groupBy(EmailDeliveryDomainObjectAbstract::CATEGORY)
            ->select(EmailDeliveryDomainObjectAbstract::CATEGORY)
            ->selectRaw('COUNT(*) AS sent')
            ->selectRaw('COUNT(delivered_at) AS delivered')
            ->selectRaw('COUNT(*) FILTER (WHERE bounce_type = ?) AS permanent_bounces', [EmailBounceType::PERMANENT->name])
            ->selectRaw('COUNT(*) FILTER (WHERE bounce_type = ?) AS transient_bounces', [EmailBounceType::TRANSIENT->name])
            ->selectRaw('COUNT(complained_at) AS complaints')
            ->selectRaw('COUNT(first_opened_at) AS opened')
            ->selectRaw('COUNT(first_clicked_at) AS clicked')
            ->orderByDesc('sent')
            ->get()
            ->map(static fn (object $row) => new EmailDeliveryCategoryStatsDTO(
                category: $row->category,
                sent: (int) $row->sent,
                delivered: (int) $row->delivered,
                permanentBounces: (int) $row->permanent_bounces,
                transientBounces: (int) $row->transient_bounces,
                complaints: (int) $row->complaints,
                opened: (int) $row->opened,
                clicked: (int) $row->clicked,
            )));
    }

    private function recordBounce(Builder $query, EmailDeliveryEventDTO $event): void
    {
        $query->where(function (Builder $unrecorded) use ($event) {
            $unrecorded->whereNull(EmailDeliveryDomainObjectAbstract::BOUNCED_AT);

            if ($event->bounceType === EmailBounceType::PERMANENT) {
                $unrecorded->orWhere(EmailDeliveryDomainObjectAbstract::BOUNCE_TYPE, EmailBounceType::TRANSIENT->name);
            }
        });

        $query->update([
            EmailDeliveryDomainObjectAbstract::BOUNCED_AT => $event->occurredAt,
            EmailDeliveryDomainObjectAbstract::BOUNCE_TYPE => $event->bounceType?->name,
        ]);
    }
}
