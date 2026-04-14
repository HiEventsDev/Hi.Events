<?php

namespace HiEvents\DomainObjects;

class OutgoingMessageDomainObject extends Generated\OutgoingMessageDomainObjectAbstract
{
    protected int $retry_count = 0;
    protected ?string $latest_retry_recipient = null;
    protected ?string $latest_retry_status = null;
    protected ?string $original_recipient = null;
    protected ?string $original_status = null;

    public function setRetryCount(int $retry_count): self
    {
        $this->retry_count = $retry_count;
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retry_count;
    }

    public function setLatestRetryRecipient(?string $latest_retry_recipient): self
    {
        $this->latest_retry_recipient = $latest_retry_recipient;
        return $this;
    }

    public function getLatestRetryRecipient(): ?string
    {
        return $this->latest_retry_recipient;
    }

    public function setLatestRetryStatus(?string $latest_retry_status): self
    {
        $this->latest_retry_status = $latest_retry_status;
        return $this;
    }

    public function getLatestRetryStatus(): ?string
    {
        return $this->latest_retry_status;
    }

    public function setOriginalRecipient(?string $original_recipient): self
    {
        $this->original_recipient = $original_recipient;
        return $this;
    }

    public function getOriginalRecipient(): ?string
    {
        return $this->original_recipient;
    }

    public function setOriginalStatus(?string $original_status): self
    {
        $this->original_status = $original_status;
        return $this;
    }

    public function getOriginalStatus(): ?string
    {
        return $this->original_status;
    }
}
