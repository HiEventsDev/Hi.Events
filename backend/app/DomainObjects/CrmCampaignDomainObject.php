<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;

class CrmCampaignDomainObject extends Generated\CrmCampaignDomainObjectAbstract
{
    private ?object $deliveryStats = null;

    private ?array $revenue = null;

    private ?string $eventTitle = null;

    private ?string $promoCode = null;

    private ?string $segmentName = null;

    private ?string $accountName = null;

    private ?int $importOnlyRecipientsCount = null;

    private ?string $accountCreatedAt = null;

    private ?string $organizerName = null;

    private ?string $previewHtml = null;

    private ?string $previewText = null;

    private bool $hasFinancialRules = false;

    private bool $deliveryReportsAvailable = true;

    private ?string $replyTo = null;

    private bool $awaitingAutomaticCheck = false;

    /**
     * @var string[]
     */
    private array $earlyAccessTicketTitles = [];

    /**
     * @var array<int, array{country: string, pending_count: int, next_sending_at: string}>
     */
    private array $smsWaiting = [];

    /**
     * @return array<int, array{country: string, pending_count: int, next_sending_at: string}>
     */
    public function getSmsWaiting(): array
    {
        return $this->smsWaiting;
    }

    /**
     * @param  array<int, array{country: string, pending_count: int, next_sending_at: string}>  $smsWaiting
     */
    public function setSmsWaiting(array $smsWaiting): static
    {
        $this->smsWaiting = $smsWaiting;

        return $this;
    }

    public function getDeliveryStats(): ?object
    {
        return $this->deliveryStats;
    }

    public function setDeliveryStats(?object $deliveryStats): static
    {
        $this->deliveryStats = $deliveryStats;

        return $this;
    }

    public function getRevenue(): ?array
    {
        return $this->revenue;
    }

    public function setRevenue(?array $revenue): static
    {
        $this->revenue = $revenue;

        return $this;
    }

    public function getEventTitle(): ?string
    {
        return $this->eventTitle;
    }

    public function setEventTitle(?string $eventTitle): static
    {
        $this->eventTitle = $eventTitle;

        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): static
    {
        $this->promoCode = $promoCode;

        return $this;
    }

    public function getSegmentName(): ?string
    {
        return $this->segmentName;
    }

    public function setSegmentName(?string $segmentName): static
    {
        $this->segmentName = $segmentName;

        return $this;
    }

    public function getImportOnlyRecipientsCount(): ?int
    {
        return $this->importOnlyRecipientsCount;
    }

    public function setImportOnlyRecipientsCount(?int $importOnlyRecipientsCount): static
    {
        $this->importOnlyRecipientsCount = $importOnlyRecipientsCount;

        return $this;
    }

    public function getAccountCreatedAt(): ?string
    {
        return $this->accountCreatedAt;
    }

    public function setAccountCreatedAt(?string $accountCreatedAt): static
    {
        $this->accountCreatedAt = $accountCreatedAt;

        return $this;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function setAccountName(?string $accountName): static
    {
        $this->accountName = $accountName;

        return $this;
    }

    public function getOrganizerName(): ?string
    {
        return $this->organizerName;
    }

    public function setOrganizerName(?string $organizerName): static
    {
        $this->organizerName = $organizerName;

        return $this;
    }

    public function getPreviewText(): ?string
    {
        return $this->previewText;
    }

    public function setPreviewText(?string $previewText): static
    {
        $this->previewText = $previewText;

        return $this;
    }

    public function getPreviewHtml(): ?string
    {
        return $this->previewHtml;
    }

    public function setPreviewHtml(?string $previewHtml): static
    {
        $this->previewHtml = $previewHtml;

        return $this;
    }

    public function getHasFinancialRules(): bool
    {
        return $this->hasFinancialRules;
    }

    public function setHasFinancialRules(bool $hasFinancialRules): static
    {
        $this->hasFinancialRules = $hasFinancialRules;

        return $this;
    }

    public function getDeliveryReportsAvailable(): bool
    {
        return $this->deliveryReportsAvailable;
    }

    public function setDeliveryReportsAvailable(bool $deliveryReportsAvailable): static
    {
        $this->deliveryReportsAvailable = $deliveryReportsAvailable;

        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setReplyTo(?string $replyTo): static
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getEarlyAccessTicketTitles(): array
    {
        return $this->earlyAccessTicketTitles;
    }

    /**
     * @param  string[]  $earlyAccessTicketTitles
     */
    public function setEarlyAccessTicketTitles(array $earlyAccessTicketTitles): static
    {
        $this->earlyAccessTicketTitles = $earlyAccessTicketTitles;

        return $this;
    }

    public function isEditable(): bool
    {
        return in_array($this->getStatus(), CrmCampaignStatus::editableValues(), true);
    }

    public function isSms(): bool
    {
        return $this->getChannel() === CrmMarketingChannel::SMS->value;
    }

    public function nobodyToSendToMessage(): string
    {
        return $this->isSms()
            ? __('Nobody in this segment has opted in to text messages in a country you send to, so there is no one to send to.')
            : __('Nobody in this segment has opted in to marketing emails, so there is no one to send to.');
    }

    public function offersEarlyAccess(): bool
    {
        return $this->getEventId() !== null
            && $this->earlyAccessProductIds() !== []
            && $this->getPromoCodeId() === null
            && $this->getAutomationId() === null;
    }

    /**
     * @return int[]
     */
    public function earlyAccessProductIds(): array
    {
        $ids = $this->getEarlyAccessProductIds();

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function isAwaitingAutomaticCheck(): bool
    {
        return $this->awaitingAutomaticCheck;
    }

    public function setAwaitingAutomaticCheck(bool $awaitingAutomaticCheck): static
    {
        $this->awaitingAutomaticCheck = $awaitingAutomaticCheck;

        return $this;
    }

    /**
     * @return array{decision: string, confidence: float, reasons: string[], reviewed_at: string}|null
     */
    public function aiReviewVerdict(): ?array
    {
        $verdict = $this->getAiReview();

        return is_array($verdict) ? $verdict : null;
    }
}
