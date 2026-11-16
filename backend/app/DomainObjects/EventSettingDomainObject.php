<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\EventFeature;

class EventSettingDomainObject extends Generated\EventSettingDomainObjectAbstract
{
    public function isFeatureEnabled(EventFeature $feature): bool
    {
        $features = $this->getEnabledFeatures();

        return is_array($features) && in_array($feature->value, $features, true);
    }

    /**
     * @todo This should not be here.
     */
    public function getGetEmailFooterHtml(): string
    {
        if ($this->getEmailFooterMessage() === null) {
            return '';
        }

        return <<<HTML
<div style="color: #888; margin-top: 30px; margin-bottom: 30px; font-size: .9em;">
    {$this->getEmailFooterMessage()}
</div>
HTML;
    }
}
