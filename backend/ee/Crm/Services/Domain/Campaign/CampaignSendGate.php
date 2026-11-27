<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\MessagingTierViolationEnum;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\NoMailableRecipientsException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignSendDecisionDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignSupportInbox;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use Illuminate\Config\Repository;

class CampaignSendGate
{
    public const REVIEW_REASON_UNTRUSTED_ACCOUNT = 'UNTRUSTED_ACCOUNT';

    public const REVIEW_REASON_FIRST_CAMPAIGN = 'FIRST_CAMPAIGN';

    public const REVIEW_REASON_RECENT_IMPORT = 'RECENT_IMPORT';

    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly MessagingEligibilityService $eligibilityService,
        private readonly Repository $config,
        private readonly CampaignSupportInbox $supportInbox,
    ) {}

    /**
     * @throws CampaignNotSendableException
     */
    public function assertAccountMaySend(int $accountId): AccountDomainObject
    {
        $account = $this->accountRepository->findById($accountId);

        if ($account->getAccountVerifiedAt() === null) {
            throw new CampaignNotSendableException(
                __('Confirm your account\'s email address before sending campaigns. Check your inbox for the confirmation email.'),
                CampaignNotSendableException::REASON_ACCOUNT_NOT_VERIFIED,
            );
        }

        if ($this->config->get('app.saas_mode_enabled') && ! $account->getIsManuallyVerified()) {
            $contact = array_filter(['email' => $this->supportInbox->address()]);

            throw new CampaignNotSendableException(
                $contact === []
                    ? __('To protect people from spam, campaigns are switched on once your account is verified. Connect Stripe or contact us to get yours enabled.')
                    : __('To protect people from spam, campaigns are switched on once your account is verified. Connect Stripe or contact us at :email.', $contact),
                CampaignNotSendableException::REASON_ACCOUNT_NOT_APPROVED,
                $contact,
            );
        }

        return $account;
    }

    public function maxRecipientsPerSend(int $accountId): int
    {
        return $this->eligibilityService
            ->getAccountMessagingTier($this->accountRepository->findById($accountId)->getAccountMessagingTierId())
            ->getMaxRecipientsPerMessage();
    }

    /**
     * @throws CampaignNotSendableException
     */
    public function evaluate(CrmCampaignDomainObject $campaign, int $recipientCount, ?CompiledSegmentDTO $audience, bool $checkDailyCap = true): CampaignSendDecisionDTO
    {
        $account = $this->assertAccountMaySend($campaign->getAccountId());
        $isSaas = (bool) $this->config->get('app.saas_mode_enabled');
        $supportEmail = $this->supportInbox->address();

        if ($recipientCount === 0) {
            throw new NoMailableRecipientsException($campaign->nobodyToSendToMessage());
        }

        $tier = $this->eligibilityService->getAccountMessagingTier($account->getAccountMessagingTierId());

        if ($checkDailyCap
            && $campaign->getAutomationId() === null
            && $this->campaignRepository->countManualStartedSince($account->getId(), now()->subDay()) >= $tier->getMaxMessagesPer24h()) {
            throw new CampaignNotSendableException(
                __('You have reached your limit of campaigns for today. Please try again tomorrow.'),
            );
        }

        $violation = $this->eligibilityService->checkTierLimits(
            $account->getId(),
            $recipientCount,
            (string) $campaign->getBody(),
        );

        foreach ($violation?->violations ?? [] as $tierViolation) {
            if ($tierViolation === MessagingTierViolationEnum::RECIPIENT_LIMIT_EXCEEDED) {
                $limit = ['count' => $recipientCount, 'max' => $tier->getMaxRecipientsPerMessage()];

                throw new CampaignNotSendableException(
                    match (true) {
                        ! $isSaas => trans_choice(
                            '{1} This campaign has :count recipient, which is more than can be sent to at once (:max). Send to a smaller segment.|[2,*] This campaign has :count recipients, which is more than can be sent to at once (:max). Send to a smaller segment.',
                            $recipientCount,
                            $limit,
                        ),
                        $supportEmail === null => trans_choice(
                            '{1} This campaign has :count recipient, which is more than your account can send to at once (:max). Contact us to raise the limit, or send to a smaller segment.|[2,*] This campaign has :count recipients, which is more than your account can send to at once (:max). Contact us to raise the limit, or send to a smaller segment.',
                            $recipientCount,
                            $limit,
                        ),
                        default => trans_choice(
                            '{1} This campaign has :count recipient, which is more than your account can send to at once (:max). Contact us at :email to raise the limit, or send to a smaller segment.|[2,*] This campaign has :count recipients, which is more than your account can send to at once (:max). Contact us at :email to raise the limit, or send to a smaller segment.',
                            $recipientCount,
                            [...$limit, 'email' => $supportEmail],
                        ),
                    },
                    CampaignNotSendableException::REASON_TOO_MANY_RECIPIENTS,
                    $limit,
                );
            }

            if ($tierViolation === MessagingTierViolationEnum::LINKS_NOT_ALLOWED) {
                throw new CampaignNotSendableException(
                    match (true) {
                        ! $isSaas => __('Your account can\'t send links yet. Remove the links to send this campaign.'),
                        $supportEmail === null => __('Your account can\'t send links yet. Remove the links, or contact us to enable them.'),
                        default => __('Your account can\'t send links yet. Remove the links, or contact us at :email to enable them.', ['email' => $supportEmail]),
                    },
                    CampaignNotSendableException::REASON_LINKS_NOT_ALLOWED,
                );
            }
        }

        if (! $isSaas) {
            return new CampaignSendDecisionDTO(requires_review: false, review_reason: null);
        }

        if ($tier->getName() === MessagingEligibilityService::UNTRUSTED_TIER_NAME) {
            return new CampaignSendDecisionDTO(requires_review: true, review_reason: self::REVIEW_REASON_UNTRUSTED_ACCOUNT);
        }

        if (! $this->campaignRepository->hasSentCampaign($account->getId())) {
            return new CampaignSendDecisionDTO(requires_review: true, review_reason: self::REVIEW_REASON_FIRST_CAMPAIGN);
        }

        if ($this->reachesUnreviewedImports($campaign, $audience)) {
            return new CampaignSendDecisionDTO(requires_review: true, review_reason: self::REVIEW_REASON_RECENT_IMPORT);
        }

        return new CampaignSendDecisionDTO(requires_review: false, review_reason: null);
    }

    private function reachesUnreviewedImports(CrmCampaignDomainObject $campaign, ?CompiledSegmentDTO $audience): bool
    {
        return $this->contactRepository->hasUnreviewedImportOptIns($campaign->getOrganizerId(), $audience);
    }
}
