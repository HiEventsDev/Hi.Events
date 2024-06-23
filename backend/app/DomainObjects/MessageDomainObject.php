<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\Helper\StringHelper;

class MessageDomainObject extends Generated\MessageDomainObjectAbstract implements IsSortable
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

    public function getSentByUser(): ?UserDomainObject
    {
        return $this->sentByUser;
    }

    public function setSentByUser(UserDomainObject $user): self
    {
        $this->sentByUser = $user;

        return $this;
    }

    public function getMessagePreview(): string
    {
        return StringHelper::previewFromHtml($this->getMessage());
    }
}
