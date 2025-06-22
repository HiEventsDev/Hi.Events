<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

class CreateEventService
{
    public function __construct(
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly EventSettingsRepositoryInterface  $eventSettingsRepository,
        private readonly OrganizerRepositoryInterface      $organizerRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly EventStatisticRepositoryInterface $eventStatisticsRepository,
        private readonly HtmlPurifierService               $purifier,
        private readonly ImageRepositoryInterface          $imageRepository,
        private readonly Repository                        $config,
        private readonly FilesystemManager                 $filesystemManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createEvent(
        EventDomainObject         $eventData,
        ?EventSettingDomainObject $eventSettings = null
    ): EventDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventData, $eventSettings) {
            $organizer = $this->getOrganizer(
                organizerId: $eventData->getOrganizerId(),
                accountId: $eventData->getAccountId()
            );

            $event = $this->handleEventCreate($eventData);

            $eventCoverCreated = $this->createEventCover($event);

            $this->createEventSettings(
                eventSettings: $eventSettings,
                event: $event,
                organizer: $organizer,
                eventCoverCreated: $eventCoverCreated,
            );

            $this->createEventStatistics($event);

            return $event;
        });
    }

    /**
     * @throws OrganizerNotFoundException
     */
    private function getOrganizer(int $organizerId, int $accountId): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerSettingDomainObject::class)
            ->findFirstWhere([
                'id' => $organizerId,
                'account_id' => $accountId,
            ]);

        if ($organizer === null) {
            throw new OrganizerNotFoundException(
                __('Organizer :id not found', ['id' => $organizerId])
            );
        }

        return $organizer;
    }

    private function handleEventCreate(EventDomainObject $eventData): EventDomainObject
    {
        return $this->eventRepository->create([
            'title' => $eventData->getTitle(),
            'organizer_id' => $eventData->getOrganizerId(),
            'start_date' => DateHelper::convertToUTC($eventData->getStartDate(), $eventData->getTimezone()),
            'end_date' => $eventData->getEndDate()
                ? DateHelper::convertToUTC($eventData->getEndDate(), $eventData->getTimezone())
                : null,
            'description' => $this->purifier->purify($eventData->getDescription()),
            'timezone' => $eventData->getTimezone(),
            'currency' => $eventData->getCurrency(),
            'category' => $eventData->getCategory(),
            'location_details' => $eventData->getLocationDetails(),
            'account_id' => $eventData->getAccountId(),
            'user_id' => $eventData->getUserId(),
            'status' => $eventData->getStatus(),
            'short_id' => IdHelper::shortId(IdHelper::EVENT_PREFIX),
            'attributes' => $eventData->getAttributes(),
        ]);
    }

    private function createEventStatistics(EventDomainObject $event): void
    {
        $this->eventStatisticsRepository->create([
            'event_id' => $event->getId(),
            'products_sold' => 0,
            'sales_total_gross' => 0,
            'sales_total_before_additions' => 0,
            'total_tax' => 0,
            'total_fee' => 0,
            'orders_created' => 0,
        ]);
    }

    /**
     * If a default cover image exists for the event category, it will be created.
     *
     * @param EventDomainObject $event
     * @return bool
     */
    private function createEventCover(EventDomainObject $event): bool
    {
        $disk = $this->config->get('filesystems.public');
        $defaultCoversPath = $this->config->get('app.event_categories_cover_images_path');

        $imageFilename = $event->getCategory() . '.jpg';
        $imagePath = $defaultCoversPath . '/' . $imageFilename;

        if (!$this->filesystemManager->disk($disk)->exists($imagePath)) {
            return false;
        }

        $this->imageRepository->create([
            'account_id' => $event->getAccountId(),
            'entity_id' => $event->getId(),
            'entity_type' => EventDomainObject::class,
            'type' => ImageType::EVENT_COVER->name,
            'filename' => $imageFilename,
            'disk' => $this->config->get('filesystems.default'),
            'path' => $imagePath,
            'size' => 139673,
            'mime_type' => 'image/jpg',
        ]);

        return true;
    }

    private function createEventSettings(
        ?EventSettingDomainObject $eventSettings,
        EventDomainObject         $event,
        OrganizerDomainObject     $organizer,
        bool                      $eventCoverCreated = false
    ): void
    {
        if ($eventSettings !== null) {
            $eventSettings->setEventId($event->getId());
            $eventSettingsArray = $eventSettings->toArray();

            unset($eventSettingsArray['id']);

            $this->eventSettingsRepository->create($eventSettingsArray);

            return;
        }

        $organizerSettings = $organizer->getOrganizerSettings();

        $this->eventSettingsRepository->create([
            'event_id' => $event->getId(),
            'homepage_background_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_content_background_color',
                '#ffffff'
            ),
            'homepage_primary_text_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_primary_text_color',
                '#000000'
            ),
            'homepage_primary_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_primary_color',
                '#7b5db8'
            ),
            'homepage_secondary_text_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_secondary_text_color',
                '#ffffff'
            ),
            'homepage_secondary_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_secondary_color',
                '#7a5eb9'
            ),
            'homepage_body_background_color' => $organizerSettings->getHomepageThemeSetting(
                'homepage_background_color',
                '#ffffff'
            ),

            'homepage_background_type' => $eventCoverCreated
                ? HomepageBackgroundType::MIRROR_COVER_IMAGE->name
                : HomepageBackgroundType::COLOR->name,
            'continue_button_text' => __('Continue'),
            'support_email' => $organizer->getEmail(),

            'payment_providers' => [PaymentProviders::STRIPE->value],
            'offline_payment_instructions' => null,

            'enable_invoicing' => false,
            'invoice_label' => __('Invoice'),
            'invoice_prefix' => 'INV-',
            'invoice_start_number' => 1,
            'require_billing_address' => false,
            'organization_name' => $organizer->getName(),
            'organization_address' => null,
            'invoice_tax_details' => null,
        ]);
    }
}
