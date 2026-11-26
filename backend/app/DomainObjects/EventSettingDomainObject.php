<?php

namespace HiEvents\DomainObjects;

use HiEvents\Helper\StringHelper;

class EventSettingDomainObject extends Generated\EventSettingDomainObjectAbstract
{
    /**
     * @todo This should not be here.
     */
    public function getGetEmailFooterHtml(): string
    {
        if ($this->getEmailFooterMessage() === null) {
            return '';
        }

        return '<div style="color: #888; margin-top: 30px; margin-bottom: 30px; font-size: .9em;">'
            .StringHelper::onOneLine($this->getEmailFooterMessage())
            .'</div>';
    }
}
