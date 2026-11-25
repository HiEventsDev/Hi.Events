<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Helper\Url;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class CampaignMailFactory
{
    private const SAMPLE_EARLY_ACCESS_CODE = 'early-preview';

    private const LAST_EVENT_TOKENS = ['last_event_title', 'last_event_date'];

    private const DELIVERY_CONTEXT_CACHE_SECONDS = 60;

    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly CampaignUnsubscribeTokenService $unsubscribeTokenService,
        private readonly CampaignHtmlSanitizer $sanitizer,
        private readonly CampaignButtonStyler $buttonStyler,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignTokenRenderer $tokenRenderer,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @throws CampaignOrganizerDeletedException
     */
    public function make(CrmCampaignDomainObject $campaign, ?int $recipientId, ?CampaignRecipientDTO $recipient = null): CampaignMail
    {
        return $this->build($campaign, $recipientId, $recipient, $campaign->getAutomationId() !== null);
    }

    /**
     * @throws CampaignOrganizerDeletedException
     */
    public function makeAutomationPreview(CrmCampaignDomainObject $campaign, ?CampaignRecipientDTO $recipient): CampaignMail
    {
        return $this->build($campaign, null, $recipient, true);
    }

    /**
     * @throws CampaignOrganizerDeletedException
     */
    private function build(CrmCampaignDomainObject $campaign, ?int $recipientId, ?CampaignRecipientDTO $recipient, bool $isAutomated): CampaignMail
    {
        ['organizer' => $organizer, 'event_context' => $eventContext, 'tokens' => $tokens] = $this->context($campaign, $recipientId, $recipient, $isAutomated);
        $body = $this->tokenRenderer->renderBody((string) $campaign->getBody(), $tokens);

        return new CampaignMail(
            subjectLine: $this->tokenRenderer->renderSubject((string) $campaign->getSubject(), $tokens),
            preheader: $campaign->getPreheader() === null ? null : $this->tokenRenderer->renderSubject($campaign->getPreheader(), $tokens),
            body: $this->onOneLine($this->buttonStyler->style((string) $this->sanitizer->sanitize($body))),
            organizerName: $this->onOneLine($organizer->getName()),
            organizer: $organizer,
            replyToAddress: $organizer->getEmail(),
            eventCard: $eventContext['event_card'] === null ? null : new CampaignEventCardDTO(...$eventContext['event_card']),
            unsubscribeUrl: $recipientId === null ? null : $this->unsubscribeTokenService->pageUrl($recipientId),
            oneClickUnsubscribeUrl: $recipientId === null ? null : $this->unsubscribeTokenService->oneClickUrl($recipientId),
            isTest: $recipientId === null,
        );
    }

    /**
     * @return array{organizer_name: string, tokens: array<string, string|null>}
     *
     * @throws CampaignOrganizerDeletedException
     */
    public function textContext(CrmCampaignDomainObject $campaign, ?int $recipientId, ?CampaignRecipientDTO $recipient): array
    {
        ['organizer' => $organizer, 'tokens' => $tokens] = $this->context($campaign, $recipientId, $recipient, $campaign->getAutomationId() !== null);

        return ['organizer_name' => $organizer->getName(), 'tokens' => $tokens];
    }

    /**
     * @return array{organizer: OrganizerDomainObject, event_context: array{event_title: string|null, event_date: string|null, event_url: string|null, event_card: array<string, string|null>|null}, tokens: array<string, string|null>}
     *
     * @throws CampaignOrganizerDeletedException
     */
    private function context(CrmCampaignDomainObject $campaign, ?int $recipientId, ?CampaignRecipientDTO $recipient, bool $isAutomated): array
    {
        $organizer = $this->organizerRepository
            ->loadRelation(new Relationship(LocationDomainObject::class, name: 'location_record'))
            ->findFirstWhere(['id' => $campaign->getOrganizerId()]);

        if ($organizer === null) {
            throw new CampaignOrganizerDeletedException(__('The organizer of this campaign has been deleted.'));
        }

        $eventContext = $recipientId === null
            ? $this->eventContext($campaign, $isAutomated, true)
            : $this->cache->remember(
                'crm:campaign:'.$campaign->getId().':mail-context:'.App::getLocale(),
                self::DELIVERY_CONTEXT_CACHE_SECONDS,
                fn (): array => $this->eventContext($campaign, $isAutomated, false),
            );
        $content = $campaign->getSubject().' '.$campaign->getPreheader().' '.$campaign->getBody();

        return [
            'organizer' => $organizer,
            'event_context' => $eventContext,
            'tokens' => [
                'first_name' => $recipient?->first_name,
                'last_name' => $recipient?->last_name,
                'organizer_name' => $organizer->getName(),
                'event_title' => $eventContext['event_title'],
                'event_date' => $eventContext['event_date'],
                'event_url' => $eventContext['event_url'],
                ...(CampaignTokenCatalog::usesAny($content, self::LAST_EVENT_TOKENS) ? $this->lastEventTokens($recipient) : []),
            ],
        ];
    }

    /**
     * @return array{event_title: string|null, event_date: string|null, event_url: string|null, event_card: array<string, string|null>|null}
     */
    private function eventContext(CrmCampaignDomainObject $campaign, bool $isAutomated, bool $isPreview): array
    {
        $occurrence = $campaign->getEventOccurrenceId() === null
            ? null
            : $this->occurrenceRepository->findFirstWhere(['id' => $campaign->getEventOccurrenceId()]);
        $eventId = $campaign->getEventId() ?? $occurrence?->getEventId();
        $event = $eventId === null ? null : $this->eventRepository->findFirstWhere(['id' => $eventId]);
        $card = $event === null || $campaign->getEventId() === null ? null : $this->eventCard($campaign, $isAutomated, $isPreview);

        return [
            'event_title' => $event?->getTitle(),
            'event_date' => $this->eventDate($event, $occurrence),
            'event_url' => $card?->url ?? ($event === null ? null : $this->eventUrl($event->getId(), $event->getTitle())),
            'event_card' => $card?->toArray(),
        ];
    }

    /**
     * @return array{last_event_title: string|null, last_event_date: string|null}
     */
    private function lastEventTokens(?CampaignRecipientDTO $recipient): array
    {
        $last = $recipient?->contact_id === null ? null : $this->campaignRepository->findLastAttendedEvent($recipient->contact_id);

        return [
            'last_event_title' => $last?->title,
            'last_event_date' => $last === null ? null : $this->formatDate((string) $last->starts_at, $last->timezone),
        ];
    }

    private function eventDate(?EventDomainObject $event, ?EventOccurrenceDomainObject $occurrence): ?string
    {
        if ($event === null) {
            return null;
        }

        $startDate = $occurrence?->getStartDate() ?? $this->campaignRepository->findCurrentOrNextOccurrenceStart($event->getId(), now());

        return $startDate === null ? null : $this->formatDate($startDate, $event->getTimezone());
    }

    private function eventUrl(int $eventId, string $title): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE), $eventId, Str::slug($title) ?: 'event');
    }

    private function formatDate(string $date, ?string $timezone): string
    {
        return Carbon::parse($date, 'UTC')
            ->setTimezone($timezone ?? 'UTC')
            ->locale(App::getLocale())
            ->isoFormat('LL');
    }

    private function onOneLine(string $text): string
    {
        return trim((string) preg_replace('/\s*\R\s*/u', ' ', $text));
    }

    private function eventCard(CrmCampaignDomainObject $campaign, bool $isAutomated, bool $isPreview): ?CampaignEventCardDTO
    {
        $card = $this->campaignRepository->findEventCard((int) $campaign->getEventId(), $campaign->getEventOccurrenceId(), now());

        if ($card === null) {
            return null;
        }

        $code = match (true) {
            $campaign->getPromoCodeId() !== null => $this->promoCodeRepository->findFirstWhere(['id' => $campaign->getPromoCodeId()])?->getCode(),
            $isPreview && ! $isAutomated && $campaign->offersEarlyAccess() => self::SAMPLE_EARLY_ACCESS_CODE,
            default => null,
        };

        $url = $this->eventUrl((int) $card->id, $card->title);

        return new CampaignEventCardDTO(
            title: $this->onOneLine($card->title),
            date_line: $card->start_date === null ? null : $this->formatDateTime((string) $card->start_date, $card->timezone),
            location_line: $this->locationLine($card),
            image_url: $card->cover_path === null ? null : Url::getCdnUrl($card->cover_path),
            url: $code === null ? $url : $url.'?promo_code='.rawurlencode($code),
            button_label: $code === null ? __('Get tickets') : __('Get early access'),
        );
    }

    private function formatDateTime(string $date, ?string $timezone): string
    {
        return Carbon::parse($date, 'UTC')
            ->setTimezone($timezone ?? 'UTC')
            ->locale(App::getLocale())
            ->isoFormat('llll');
    }

    private function locationLine(object $card): ?string
    {
        if ($card->location_type === LocationType::ONLINE->name) {
            return __('Online');
        }

        $parts = array_filter([$card->location_name, $card->city], static fn ($part) => is_string($part) && trim($part) !== '');

        return $parts === [] ? null : $this->onOneLine(implode(', ', array_unique($parts)));
    }
}
