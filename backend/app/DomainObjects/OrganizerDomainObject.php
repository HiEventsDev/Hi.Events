<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\StripePlatform;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizerDomainObject extends Generated\OrganizerDomainObjectAbstract
{
    private ?Collection $images = null;

    /**
     * @return Collection<EventDomainObject>|null
     */
    private ?Collection $events = null;

    private ?OrganizerSettingDomainObject $settings = null;

    /** @var Collection<int, OrganizerStripePlatformDomainObject>|null */
    private ?Collection $stripePlatforms = null;

    private ?OrganizerVatSettingDomainObject $vatSetting = null;

    private ?OrganizerConfigurationDomainObject $configuration = null;

    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function setImages(?Collection $images): self
    {
        $this->images = $images;

        return $this;
    }

    public function getEvents(): ?Collection
    {
        return $this->events;
    }

    public function setEvents(?Collection $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function getOrganizerSettings(): ?OrganizerSettingDomainObject
    {
        return $this->settings;
    }

    public function setOrganizerSettings(?OrganizerSettingDomainObject $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function getOrganizerStripePlatforms(): ?Collection
    {
        return $this->stripePlatforms;
    }

    public function setOrganizerStripePlatforms(Collection $stripePlatforms): self
    {
        $this->stripePlatforms = $stripePlatforms;

        return $this;
    }

    public function getSlug(): string
    {
        return Str::slug($this->name);
    }

    public function getPrimaryStripePlatform(): ?OrganizerStripePlatformDomainObject
    {
        if (!$this->stripePlatforms || $this->stripePlatforms->isEmpty()) {
            return null;
        }

        return $this->stripePlatforms
            ->filter(fn($platform) => $platform->getStripeSetupCompletedAt() !== null)
            ->sortByDesc(fn($platform) => $platform->getCreatedAt())
            ->first();
    }

    public function getStripePlatformByType(?StripePlatform $platformType): ?OrganizerStripePlatformDomainObject
    {
        if (!$this->stripePlatforms || $this->stripePlatforms->isEmpty()) {
            return null;
        }

        return $this->stripePlatforms
            ->filter(fn($platform) => $platform->getStripeConnectPlatform() === $platformType?->value)
            ->first();
    }

    public function getActiveStripeAccountId(): ?string
    {
        return $this->getPrimaryStripePlatform()?->getStripeAccountId();
    }

    public function getActiveStripePlatform(): ?StripePlatform
    {
        $primaryPlatform = $this->getPrimaryStripePlatform();
        if (!$primaryPlatform || !$primaryPlatform->getStripeConnectPlatform()) {
            return null;
        }

        return StripePlatform::fromString($primaryPlatform->getStripeConnectPlatform());
    }

    public function isStripeSetupComplete(): bool
    {
        return $this->getPrimaryStripePlatform() !== null;
    }

    public function getOrganizerVatSetting(): ?OrganizerVatSettingDomainObject
    {
        return $this->vatSetting;
    }

    public function setOrganizerVatSetting(?OrganizerVatSettingDomainObject $vatSetting): self
    {
        $this->vatSetting = $vatSetting;

        return $this;
    }

    public function getOrganizerConfiguration(): ?OrganizerConfigurationDomainObject
    {
        return $this->configuration;
    }

    public function setOrganizerConfiguration(?OrganizerConfigurationDomainObject $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }
}
