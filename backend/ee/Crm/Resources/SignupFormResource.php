<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmSignupFormDomainObject
 */
class SignupFormResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'public_id' => $this->getPublicId(),
            'name' => $this->getName(),
            /** @var 'ACTIVE'|'PAUSED' */
            'status' => $this->getStatus(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'button_label' => $this->getButtonLabel(),
            'success_message' => $this->getSuccessMessage(),
            'asks_for_name' => $this->getAsksForName(),
            'privacy_url' => $this->getPrivacyUrl(),
            /** @var int[] */
            'tag_ids' => $this->getTagIds() ?? [],
            'show_on_organizer_page' => $this->getShowOnOrganizerPage(),
            'waiting_count' => $this->getWaitingCount(),
            'submissions_count' => $this->getSubmissionsCount(),
            'confirmations_count' => $this->getConfirmationsCount(),
            'last_confirmed_at' => $this->getLastConfirmedAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
