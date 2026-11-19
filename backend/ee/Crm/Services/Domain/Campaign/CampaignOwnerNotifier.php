<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Generated\AccountUserDomainObjectAbstract;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Mail\CampaignRejectedMail;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Traits\Localizable;

class CampaignOwnerNotifier
{
    use Localizable;

    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Mailer $mailer,
    ) {}

    public function notifyRejected(CrmCampaignDomainObject $campaign, ?string $note): void
    {
        $this->send($campaign, new CampaignRejectedMail(
            campaignName: $this->campaignName($campaign),
            note: $note,
            actionUrl: $campaign->getAutomationId() === null
                ? $this->campaignUrl($campaign)
                : sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_AUTOMATIONS), $campaign->getOrganizerId()),
            isAutomationRun: $campaign->getAutomationId() !== null,
        ));
    }

    public function notifyNotSent(CrmCampaignDomainObject $campaign, string $reason): void
    {
        $this->send($campaign, new CampaignNotSentMail(
            campaignName: $this->campaignName($campaign),
            reason: $reason,
            campaignUrl: $this->campaignUrl($campaign),
            wasPartlySent: false,
        ));
    }

    public function notifyPausedForHealth(CrmCampaignDomainObject $campaign): void
    {
        $this->inOwnerLocale($campaign, fn () => $this->send($campaign, new CampaignNotSentMail(
            campaignName: $this->campaignName($campaign),
            reason: $campaign->getStatusReason() === CampaignHealthGuard::REASON_COMPLAINTS
                ? __('Too many recipients marked it as spam.')
                : __('Too many addresses rejected it.'),
            campaignUrl: $this->campaignUrl($campaign),
            wasPartlySent: true,
        )));
    }

    /**
     * @param  callable(): string  $reason
     */
    public function notifyStoppedSending(CrmCampaignDomainObject $campaign, callable $reason): void
    {
        $this->inOwnerLocale($campaign, fn () => $this->send($campaign, new CampaignNotSentMail(
            campaignName: $this->campaignName($campaign),
            reason: $reason(),
            campaignUrl: $this->campaignUrl($campaign),
            wasPartlySent: true,
        )));
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function inOwnerLocale(CrmCampaignDomainObject $campaign, callable $callback): mixed
    {
        $recipient = $this->recipient($campaign);

        return $this->withLocale(
            $recipient === null ? null : $this->userRepository->findById($recipient->getUserId())->getLocale(),
            $callback,
        );
    }

    private function send(CrmCampaignDomainObject $campaign, BaseMail $mail): void
    {
        $recipient = $this->recipient($campaign);

        if ($recipient === null) {
            return;
        }

        $user = $this->userRepository->findById($recipient->getUserId());

        $this->mailer->to($user->getEmail())->locale($user->getLocale())->send($mail);
    }

    private function campaignName(CrmCampaignDomainObject $campaign): string
    {
        return (string) ($campaign->getSubject() ?: $campaign->getName());
    }

    private function campaignUrl(CrmCampaignDomainObject $campaign): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_CAMPAIGN), $campaign->getOrganizerId(), $campaign->getId());
    }

    private function recipient(CrmCampaignDomainObject $campaign): ?AccountUserDomainObject
    {
        $active = [
            AccountUserDomainObjectAbstract::ACCOUNT_ID => $campaign->getAccountId(),
            AccountUserDomainObjectAbstract::STATUS => UserStatus::ACTIVE->name,
        ];

        $creator = $campaign->getCreatedByUserId() === null ? null : $this->accountUserRepository->findFirstWhere([
            ...$active,
            AccountUserDomainObjectAbstract::USER_ID => $campaign->getCreatedByUserId(),
        ]);

        return $creator ?? $this->accountUserRepository->findFirstWhere([
            ...$active,
            AccountUserDomainObjectAbstract::IS_ACCOUNT_OWNER => true,
        ]);
    }
}
