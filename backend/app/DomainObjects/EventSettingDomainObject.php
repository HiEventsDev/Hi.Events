<?php

namespace HiEvents\DomainObjects;

class EventSettingDomainObject extends Generated\EventSettingDomainObjectAbstract
{
    /**
     * @return string
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
