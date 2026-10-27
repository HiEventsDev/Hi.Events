<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use Illuminate\Support\Str;

class MailBrandingService
{
    private const MAX_SENDER_NAME_LENGTH = 70;

    public function __construct(
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly EmailAccentColorService $emailAccentColorService,
    ) {}

    public function forMail(BaseMail $mail): MailBrandingDTO
    {
        $event = $mail instanceof ContainsEventContent ? $mail->getContentEvent() : null;

        $isHiEvents = $this->accountSubscriptionAccessService->areSubscriptionsAvailable();

        $hasSubscription = $isHiEvents
            && $event !== null
            && $this->accountSubscriptionAccessService->hasActiveSubscription($event->getAccountId());

        $showPromo = $isHiEvents && $mail->showsHiEventsPromo() && ! $hasSubscription;

        $organizer = $hasSubscription ? $this->findOrganizer($event) : null;

        if ($organizer === null) {
            return new MailBrandingDTO(
                hideHiEventsBranding: false,
                showHiEventsPromo: $showPromo,
                promoUrl: $this->promoUrl(),
            );
        }

        $accent = $this->emailAccentColorService->fromAccent($this->eventAccent($event));

        return new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: $this->promoUrl(),
            organizerName: $organizer->getName(),
            organizerLogoUrl: $this->organizerLogoUrl($organizer),
            organizerUrl: $organizer->getWebsite(),
            senderName: $mail->isSentToBuyers() ? $this->senderName($organizer->getName()) : null,
            buttonColor: $accent?->buttonColor,
            buttonTextColor: $accent?->buttonTextColor,
            linkColor: $accent?->linkColor,
        );
    }

    private function eventAccent(EventDomainObject $event): ?string
    {
        /** @var EventSettingDomainObject|null $settings */
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $event->getId(),
        ]);

        $accent = $settings?->getHomepageThemeSettings()['accent'] ?? null;

        return is_string($accent) ? $accent : null;
    }

    private function senderName(string $organizerName): ?string
    {
        if (str_contains($organizerName, '@') || preg_match('/[\p{Cf}\x{80}-\x{9F}]/u', $organizerName)) {
            return null;
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $organizerName));

        if ($name === '' || $this->mentionsPlatform($name)) {
            return null;
        }

        return Str::limit($name, self::MAX_SENDER_NAME_LENGTH, '');
    }

    private function mentionsPlatform(string $name): bool
    {
        $comparable = $this->lettersAndDigits($name);

        return collect(['Hi.Events', (string) config('app.name')])
            ->map(fn (string $platformName) => $this->lettersAndDigits($platformName))
            ->filter()
            ->contains(fn (string $platformName) => str_contains($comparable, $platformName));
    }

    private function lettersAndDigits(string $value): string
    {
        return Str::lower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }

    private function findOrganizer(EventDomainObject $event): ?OrganizerDomainObject
    {
        if ($event->getOrganizerId() === null) {
            return null;
        }

        /** @var OrganizerDomainObject|null */
        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findFirstWhere([
                'id' => $event->getOrganizerId(),
                'account_id' => $event->getAccountId(),
            ]);
    }

    private function organizerLogoUrl(OrganizerDomainObject $organizer): ?string
    {
        $logo = $organizer->getImages()?->first(
            fn (ImageDomainObject $image) => $image->getType() === ImageType::ORGANIZER_LOGO->name
        );

        return $logo ? Url::getCdnUrl($logo->getPath()) : null;
    }

    private function promoUrl(): string
    {
        return Url::getFrontEndUrlFromConfig(Url::REGISTER, [
            'utm_source' => 'email-promo',
            'utm_medium' => 'email',
            'utm_campaign' => 'create-your-event',
        ]);
    }
}
