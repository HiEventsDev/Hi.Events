<?php

namespace HiEvents\DomainObjects;

use BackedEnum;
use UnitEnum;

class OrganizerSettingDomainObject extends Generated\OrganizerSettingDomainObjectAbstract
{
    public function getSocialMediaHandle(string $platform): ?string
    {
        $handles = $this->getSocialMediaHandles();

        return $handles[$platform] ?? null;
    }

    public function getHomepageThemeSetting(string $key, string $default = ''): ?string
    {
        $settings = $this->getHomepageThemeSettings();

        if (isset($settings[$key]) && ($settings[$key] instanceof UnitEnum)) {
            return $settings[$key] instanceof BackedEnum
                ? $settings[$key]->value
                : $settings[$key]->name;
        }

        return $settings[$key] ?? $default;
    }
}
