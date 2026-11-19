<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmSignupFormDomainObject
 */
class SignupFormPublicResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'public_id' => $this->getPublicId(),
            'organizer_id' => $this->getOrganizerId(),
            'organizer_name' => $this->getOrganizer()?->getName(),
            'organizer_slug' => $this->getOrganizer()?->getSlug(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'button_label' => $this->getButtonLabel(),
            'success_message' => $this->getSuccessMessage(),
            'asks_for_name' => $this->getAsksForName(),
            'privacy_url' => $this->getPrivacyUrl(),
        ];
    }
}
