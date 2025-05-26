<?php

namespace HiEvents\DomainObjects;

class OrganizerSettingDomainObject extends Generated\OrganizerSettingDomainObjectAbstract
{
    public function getSocialMediaHandle(string $platform): ?string
    {
        $handles = $this->getSocialMediaHandles();

        return $handles[$platform] ?? null;
    }

    public function getHomepageThemeSetting(string $key): ?string
    {
        $settings = $this->getHomepageThemeSettings();

        return $settings[$key] ?? null;
    }
}
