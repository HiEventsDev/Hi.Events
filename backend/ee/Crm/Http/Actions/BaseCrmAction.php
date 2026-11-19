<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Request\PreviewAutomationRequest;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\PreviewAutomationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

abstract class BaseCrmAction extends BaseAction
{
    protected function authorizeCrm(int $organizerId): void
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);
    }

    /**
     * @throws FeatureNotEnabledException
     */
    protected function authorizeCrmChange(int $organizerId): void
    {
        $this->authorizeCrm($organizerId);

        app(FeatureFlagService::class)->assertEnabled(FeatureFlag::CRM, $this->getAuthenticatedAccountId());
    }

    protected function canViewFinancials(): bool
    {
        return ! $this->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS);
    }

    /**
     * @throws ValidationException
     */
    protected function segmentReference(Request $request): SegmentReferenceDTO
    {
        $definition = $request->input('definition');

        if (is_string($definition)) {
            try {
                $definition = json_decode($definition, true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['definition' => __('The segment rules are not valid.')]);
            }
        }

        $segmentId = $request->input('segment_id');
        $preset = $request->input('preset');

        if (($segmentId !== null && ! is_scalar($segmentId)) || ($preset !== null && ! is_string($preset))) {
            throw ValidationException::withMessages(['segment' => __('The segment is not valid.')]);
        }

        return new SegmentReferenceDTO(
            segment_id: $request->filled('segment_id') ? (int) $segmentId : null,
            preset: $request->filled('preset') ? $preset : null,
            definition: is_array($definition) ? $definition : null,
        );
    }

    protected function previewFrom(PreviewAutomationRequest $request, int $organizerId): PreviewAutomationDTO
    {
        $trigger = CrmAutomationTrigger::from($request->validated('trigger'));

        return new PreviewAutomationDTO(
            account_id: $this->getAuthenticatedAccountId(),
            organizer_id: $organizerId,
            trigger: $trigger,
            subject: $request->validated('subject'),
            preheader: $request->validated('preheader'),
            body: $request->validated('body'),
            segment_id: $request->validated('segment_id') === null ? null : (int) $request->validated('segment_id'),
            preset: $request->validated('preset'),
            config: $request->config($trigger),
            can_view_financials: $this->canViewFinancials(),
            viewer_name: $this->signedInRecipientName(),
            automation_id: $request->validated('automation_id') === null ? null : (int) $request->validated('automation_id'),
        );
    }

    protected function signedInRecipientName(): CampaignRecipientDTO
    {
        $user = $this->getAuthenticatedUser();

        return new CampaignRecipientDTO(first_name: $user->getFirstName(), last_name: $user->getLastName());
    }

    protected function invalidSegment(InvalidSegmentDefinitionException $exception): ValidationException
    {
        return ValidationException::withMessages($exception->errors());
    }
}
