<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\Helper\StringHelper;

class MessageDomainObject extends Generated\MessageDomainObjectAbstract implements IsSortable, IsFilterable
{
    private ?UserDomainObject $sentByUser = null;

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Sent Date Oldest'),
                    'desc' => __('Sent Date Newest'),
                ],
                self::SUBJECT => [
                    'asc' => __('Subject A-Z'),
                    'desc' => __('Subject Z-A'),
                ],
            ],
        );

    }

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
        ];
    }

    public function getSentByUser(): ?UserDomainObject
    {
        return $this->sentByUser;
    }

    public function setSentByUser(UserDomainObject $user): self
    {
        $this->sentByUser = $user;

        return $this;
    }

    public function isTest(): bool
    {
        $sendData = $this->getSendData();

        if (is_string($sendData)) {
            $sendData = json_decode($sendData, true);
        }

        return is_array($sendData) && ($sendData['is_test'] ?? false) === true;
    }

    public function getMessagePreview(): string
    {
        return StringHelper::previewFromHtml($this->getMessage());
    }

    /**
     * @return array{decision: string, confidence: float, reasons: string[], reviewed_at: string}|null
     */
    public function aiReviewVerdict(): ?array
    {
        $verdict = $this->getAiReview();

        return is_array($verdict) ? $verdict : null;
    }

    /**
     * @return string[]
     */
    public function eligibilityFailureValues(): array
    {
        $failures = $this->getEligibilityFailures();

        return is_array($failures) ? array_values(array_map('strval', $failures)) : [];
    }
}
