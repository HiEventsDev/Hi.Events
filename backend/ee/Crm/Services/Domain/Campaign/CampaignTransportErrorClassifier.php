<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use Aws\Exception\AwsException;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Enums\CrmTransportVerdict;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

class CampaignTransportErrorClassifier
{
    private const HTTP_STATUSES_NOTHING_WAS_ACCEPTED_ON = [408, 429, 503];

    private const AWS_THROTTLING_ERROR_CODES = ['Throttling', 'ThrottlingException', 'TooManyRequestsException', 'LimitExceededException'];

    private const POSTMARK_UNPROCESSABLE_STATUS = 422;

    private const POSTMARK_INACTIVE_RECIPIENT_CODE = 406;

    private const SMTP_CONNECTION_NEVER_OPENED_PREFIX = 'Connection could not be established with host';

    private const SMTP_TRANSCRIPT_LINE = '/^\[[^\]]+\] [<>] /m';

    private const SMTP_ENVELOPE_STARTED = '/^\[[^\]]+\] > MAIL FROM:/m';

    private const SMTP_MESSAGE_BODY_STARTED = '/^\[[^\]]+\] > DATA\s*$/m';

    private const SMTP_MAILBOX_DOES_NOT_EXIST = '/\b5\.(?:1\.(?:1|2|3|6|10)|2\.1)\b/';

    public function permanentFailureReason(TransportExceptionInterface $exception): ?CrmRecipientFailureReason
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            $verdict = $this->verdictOn($cause);

            if ($verdict !== null) {
                return $verdict->failureReason();
            }
        }

        return CrmRecipientFailureReason::TIMEOUT;
    }

    private function verdictOn(Throwable $cause): ?CrmTransportVerdict
    {
        if ($cause instanceof HttpTransportException) {
            return $this->verdictOnApiResponse($cause);
        }

        if ($cause instanceof AwsException) {
            return in_array($cause->getAwsErrorCode(), self::AWS_THROTTLING_ERROR_CODES, true)
                ? CrmTransportVerdict::NOT_ACCEPTED
                : $this->verdictOnHttpStatus($cause->getStatusCode());
        }

        if (str_starts_with($cause->getMessage(), self::SMTP_CONNECTION_NEVER_OPENED_PREFIX)) {
            return CrmTransportVerdict::NOT_ACCEPTED;
        }

        if (! $cause instanceof TransportExceptionInterface) {
            return null;
        }

        $transcript = $cause->getDebug();

        if (preg_match(self::SMTP_TRANSCRIPT_LINE, $transcript) !== 1) {
            return $cause instanceof UnexpectedResponseException ? $this->verdictOnSmtpReply($cause, false) : null;
        }

        if (preg_match(self::SMTP_ENVELOPE_STARTED, $transcript) !== 1) {
            return CrmTransportVerdict::NOT_ACCEPTED;
        }

        $bodyStarted = preg_match(self::SMTP_MESSAGE_BODY_STARTED, $transcript) === 1;

        return $this->verdictOnSmtpReply($cause, ! $bodyStarted)
            ?? ($bodyStarted ? CrmTransportVerdict::MAYBE_ACCEPTED : CrmTransportVerdict::NOT_ACCEPTED);
    }

    private function verdictOnSmtpReply(TransportExceptionInterface $cause, bool $refusedWhileNamingRecipients): ?CrmTransportVerdict
    {
        $replyCode = (int) $cause->getCode();

        return match (true) {
            $replyCode >= 400 && $replyCode < 500 => CrmTransportVerdict::NOT_ACCEPTED,
            $replyCode >= 500 && $replyCode < 600 => $refusedWhileNamingRecipients && preg_match(self::SMTP_MAILBOX_DOES_NOT_EXIST, $cause->getMessage()) === 1
                ? CrmTransportVerdict::ADDRESS_REFUSED
                : CrmTransportVerdict::REFUSED,
            default => null,
        };
    }

    private function verdictOnApiResponse(HttpTransportException $exception): CrmTransportVerdict
    {
        $response = $exception->getResponse();

        try {
            $status = $response->getStatusCode();
        } catch (Throwable) {
            return $this->requestBodyNeverLeft($response->getInfo())
                ? CrmTransportVerdict::NOT_ACCEPTED
                : CrmTransportVerdict::MAYBE_ACCEPTED;
        }

        if ($status === self::POSTMARK_UNPROCESSABLE_STATUS && $this->postmarkErrorCode($exception) === self::POSTMARK_INACTIVE_RECIPIENT_CODE) {
            return CrmTransportVerdict::ADDRESS_REFUSED;
        }

        return $this->verdictOnHttpStatus($status);
    }

    private function verdictOnHttpStatus(?int $status): CrmTransportVerdict
    {
        return match (true) {
            in_array($status, self::HTTP_STATUSES_NOTHING_WAS_ACCEPTED_ON, true) => CrmTransportVerdict::NOT_ACCEPTED,
            $status >= 400 && $status < 500 => CrmTransportVerdict::REFUSED,
            default => CrmTransportVerdict::MAYBE_ACCEPTED,
        };
    }

    private function requestBodyNeverLeft(mixed $transferInfo): bool
    {
        return is_array($transferInfo)
            && isset($transferInfo['size_upload'])
            && (float) $transferInfo['size_upload'] === 0.0;
    }

    private function postmarkErrorCode(HttpTransportException $exception): int
    {
        try {
            return (int) ($exception->getResponse()->toArray(false)['ErrorCode'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
