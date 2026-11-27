<?php

namespace HiEvents\DomainObjects;

class CrmContactActivityDomainObject extends Generated\CrmContactActivityDomainObjectAbstract
{
    private ?string $eventTitle = null;

    private ?string $productTitle = null;

    private ?string $occurrenceStartDate = null;

    private ?string $eventTimezone = null;

    private ?string $campaignChannel = null;

    public function getEventTitle(): ?string
    {
        return $this->eventTitle;
    }

    public function setEventTitle(?string $eventTitle): static
    {
        $this->eventTitle = $eventTitle;

        return $this;
    }

    public function getProductTitle(): ?string
    {
        return $this->productTitle;
    }

    public function setProductTitle(?string $productTitle): static
    {
        $this->productTitle = $productTitle;

        return $this;
    }

    public function getOccurrenceStartDate(): ?string
    {
        return $this->occurrenceStartDate;
    }

    public function setOccurrenceStartDate(?string $occurrenceStartDate): static
    {
        $this->occurrenceStartDate = $occurrenceStartDate;

        return $this;
    }

    public function getEventTimezone(): ?string
    {
        return $this->eventTimezone;
    }

    public function setEventTimezone(?string $eventTimezone): static
    {
        $this->eventTimezone = $eventTimezone;

        return $this;
    }

    public function getCampaignChannel(): ?string
    {
        return $this->campaignChannel;
    }

    public function setCampaignChannel(?string $campaignChannel): static
    {
        $this->campaignChannel = $campaignChannel;

        return $this;
    }
}
