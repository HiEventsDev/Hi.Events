<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmContactActivityDomainObject;
use HiEvents\DomainObjects\CrmContactActivityDomainObject as Activity;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactActivityRepositoryInterface;
use HiEvents\Models\CrmContactActivity;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<CrmContactActivityDomainObject>
 */
class CrmContactActivityRepository extends BaseRepository implements CrmContactActivityRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmContactActivity::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactActivityDomainObject::class;
    }

    public function findForContact(int $contactId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($contactId, $page, $perPage) {
            $paginator = $this->handleResults($this->model
                ->where('contact_id', $contactId)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(perPage: min($perPage, 100), page: $page));

            $activities = collect($paginator->items());

            $events = $this->db->table('events')
                ->whereIn('id', $activities->map(static fn (Activity $activity) => $activity->getEventId())->filter()->unique()->all())
                ->get(['id', 'title', 'timezone'])
                ->keyBy('id');

            $products = $this->db->table('products')
                ->whereIn('id', $activities->map(static fn (Activity $activity) => $activity->getProductId())->filter()->unique()->all())
                ->pluck('title', 'id');

            $occurrences = $this->db->table('event_occurrences')
                ->whereIn('id', $activities->map(static fn (Activity $activity) => $activity->getEventOccurrenceId())->filter()->unique()->all())
                ->pluck('start_date', 'id');

            $campaignChannels = $this->db->table('crm_campaign_recipients')
                ->join('crm_campaigns', 'crm_campaigns.id', '=', 'crm_campaign_recipients.campaign_id')
                ->whereIn('crm_campaign_recipients.id', $activities
                    ->filter(static fn (Activity $activity) => $activity->getSourceType() === CrmActivitySource::CAMPAIGN_RECIPIENT->value)
                    ->map(static fn (Activity $activity) => $activity->getSourceId())
                    ->all())
                ->pluck('crm_campaigns.channel', 'crm_campaign_recipients.id');

            $activities->each(static fn (Activity $activity) => $activity
                ->setEventTitle($events[$activity->getEventId()]->title ?? null)
                ->setEventTimezone($events[$activity->getEventId()]->timezone ?? null)
                ->setProductTitle($products[$activity->getProductId()] ?? null)
                ->setOccurrenceStartDate($occurrences[$activity->getEventOccurrenceId()] ?? null)
                ->setCampaignChannel($activity->getSourceType() === CrmActivitySource::CAMPAIGN_RECIPIENT->value
                    ? ($campaignChannels[$activity->getSourceId()] ?? null)
                    : null));

            return $paginator;
        });
    }
}
