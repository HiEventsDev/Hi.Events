<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmCampaignDomainObject
 */
class AdminCampaignResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'subject' => $this->getSubject(),
            /** @var 'EMAIL'|'SMS' */
            'channel' => $this->getChannel(),
            'preview_html' => $this->getPreviewHtml(),
            'preview_text' => $this->getPreviewText(),
            'status_reason' => $this->getStatusReason(),
            /** @var array{decision: 'APPROVED'|'NEEDS_PERSON', confidence: float, reasons: string[], reviewed_at: string}|null */
            'ai_review' => $this->aiReviewVerdict(),
            'awaiting_automatic_check' => $this->isAwaitingAutomaticCheck(),
            'audience_label' => $this->getSegmentName(),
            'preset' => $this->getPresetSegment(),
            'reply_to' => $this->getReplyTo(),
            'event_title' => $this->getEventTitle(),
            /** @var string[] */
            'early_access_tickets' => $this->getEarlyAccessTicketTitles(),
            'account_id' => $this->getAccountId(),
            'account_name' => $this->getAccountName(),
            'organizer_id' => $this->getOrganizerId(),
            'organizer_name' => $this->getOrganizerName(),
            'recipients_count' => $this->getRecipientsCount(),
            'import_only_recipients_count' => $this->getImportOnlyRecipientsCount(),
            'account_created_at' => $this->getAccountCreatedAt(),
            'scheduled_at' => $this->getScheduledAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
