<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use HiEvents\DomainObjects\Generated\EmailSuppressionDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EmailSuppressionReasonEnum;
use HiEvents\DomainObjects\Status\EmailSuppressionSourceEnum;
use HiEvents\Repository\Interfaces\EmailSuppressionRepositoryInterface;

class EmailSuppressionService
{
    public function __construct(
        private readonly EmailSuppressionRepositoryInterface $emailSuppressionRepository,
    )
    {
    }

    /**
     * Check if an email is suppressed for a given email type.
     *
     * - Permanent bounces suppress ALL email types (address doesn't exist)
     * - Complaints suppress only marketing emails (customer may still need transactional)
     * - Transient bounces suppress only marketing emails (may succeed on retry for transactional)
     */
    public function isEmailSuppressed(string $email, ?int $accountId, string $emailType = 'marketing'): bool
    {
        if (!config('services.ses.suppression_enabled', false)) {
            return false;
        }

        $suppressions = $this->emailSuppressionRepository->findByEmail($email, $accountId);

        if ($suppressions->isEmpty()) {
            return false;
        }

        foreach ($suppressions as $suppression) {
            /** @var EmailSuppressionDomainObject $suppression */
            if ($suppression->getReason() === EmailSuppressionReasonEnum::BOUNCE->value) {
                if ($suppression->getBounceType() === 'Permanent') {
                    // Permanent bounce: suppress for ALL email types
                    return true;
                }

                if ($emailType === 'marketing') {
                    // Transient/Undetermined bounce: suppress only marketing
                    return true;
                }
            }

            if ($suppression->getReason() === EmailSuppressionReasonEnum::COMPLAINT->value) {
                if ($emailType === 'marketing') {
                    // Complaint: suppress only marketing
                    return true;
                }
                // Transactional emails to complaint addresses still send
            }
        }

        return false;
    }

    public function suppressEmail(
        string  $email,
        string  $reason,
        string  $source,
        ?int    $accountId = null,
        ?string $bounceType = null,
        ?string $bounceSubType = null,
        ?string $complaintType = null,
        ?string $snsMessageId = null,
        mixed   $rawPayload = null,
    ): EmailSuppressionDomainObject
    {
        return $this->emailSuppressionRepository->findOrCreateSuppression(
            uniqueAttributes: [
                EmailSuppressionDomainObjectAbstract::EMAIL => strtolower($email),
                EmailSuppressionDomainObjectAbstract::ACCOUNT_ID => $accountId,
                EmailSuppressionDomainObjectAbstract::REASON => $reason,
            ],
            additionalValues: [
                EmailSuppressionDomainObjectAbstract::BOUNCE_TYPE => $bounceType,
                EmailSuppressionDomainObjectAbstract::BOUNCE_SUB_TYPE => $bounceSubType,
                EmailSuppressionDomainObjectAbstract::COMPLAINT_TYPE => $complaintType,
                EmailSuppressionDomainObjectAbstract::SOURCE => $source,
                EmailSuppressionDomainObjectAbstract::SNS_MESSAGE_ID => $snsMessageId,
                EmailSuppressionDomainObjectAbstract::RAW_PAYLOAD => $rawPayload ? json_encode($rawPayload) : null,
            ],
        );
    }

    public function removeSuppressionById(int $id): void
    {
        $this->emailSuppressionRepository->deleteById($id);
    }

    public function removeSuppression(string $email, ?int $accountId = null, ?string $reason = null): void
    {
        $where = [
            'email' => strtolower($email),
        ];

        if ($accountId !== null) {
            $where['account_id'] = $accountId;
        }

        if ($reason !== null) {
            $where['reason'] = $reason;
        }

        $suppressions = $this->emailSuppressionRepository->findWhere($where);

        foreach ($suppressions as $suppression) {
            $this->emailSuppressionRepository->deleteById($suppression->getId());
        }
    }
}
