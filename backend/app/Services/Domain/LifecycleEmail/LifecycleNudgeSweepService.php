<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeInterface;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class LifecycleNudgeSweepService
{
    private const BATCH_LIMIT = 25;

    private const HTTP_TOO_MANY_REQUESTS = 429;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
        private readonly LifecycleEmailTemplateService $templateService,
        private readonly LifecycleNudgeRegistry $nudgeRegistry,
        private readonly LifecycleEmailSender $sender,
        private readonly LoggerInterface $logger,
    ) {}

    public function sweep(): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        $templates = $this->templateService->findAll()
            ->filter(fn (LifecycleEmailTemplateDTO $template) => $template->enabledAt !== null)
            ->keyBy(fn (LifecycleEmailTemplateDTO $template) => $template->nudgeType->name);

        $sent = 0;
        $nudgedEmails = [];

        foreach ($this->nudgeRegistry->all() as $nudge) {
            $template = $templates->get($nudge->type()->name);

            if ($template === null || ! $this->templateRenders($nudge, $template)) {
                continue;
            }

            foreach ($nudge->findCandidates(Carbon::parse($template->enabledAt), self::BATCH_LIMIT) as $candidate) {
                if (isset($nudgedEmails[$candidate->email])
                    || ! $this->nudgeRepository->claim($candidate->accountId, $nudge->type(), $candidate->eventId)) {
                    continue;
                }

                $nudgedEmails[$candidate->email] = true;

                try {
                    $providerMessageId = $this->sender->send($candidate->email, $this->templateService->buildMail($template, $candidate->recipient));
                    $sent++;
                } catch (Throwable $e) {
                    if ($this->isTransient($e)) {
                        $this->nudgeRepository->release($candidate->accountId, $nudge->type());
                        $this->logger->error('Lifecycle email provider unavailable, stopping the sweep until the next run', [
                            'account_id' => $candidate->accountId,
                            'nudge_type' => $nudge->type()->name,
                            'error' => $this->describe($e),
                        ]);

                        break 2;
                    }

                    $this->logger->warning('Lifecycle nudge was not delivered and will not be retried', [
                        'account_id' => $candidate->accountId,
                        'nudge_type' => $nudge->type()->name,
                        'error' => $this->describe($e),
                    ]);

                    continue;
                }

                if ($providerMessageId !== null) {
                    $this->recordProviderMessageId($candidate->accountId, $nudge, $providerMessageId);
                }
            }
        }

        if ($sent > 0) {
            $this->logger->info('Lifecycle nudges sent', ['sent_count' => $sent]);
        }
    }

    private function recordProviderMessageId(int $accountId, LifecycleNudgeInterface $nudge, string $providerMessageId): void
    {
        try {
            $this->nudgeRepository->recordProviderMessageId($accountId, $nudge->type(), $providerMessageId);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to record the provider message id of a lifecycle nudge', [
                'account_id' => $accountId,
                'nudge_type' => $nudge->type()->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function templateRenders(LifecycleNudgeInterface $nudge, LifecycleEmailTemplateDTO $template): bool
    {
        try {
            $this->templateService->buildMail($template, $nudge->sampleRecipient());

            return true;
        } catch (EmailTemplateValidationException $e) {
            $this->logger->error('Lifecycle email template does not render, skipping it', [
                'nudge_type' => $nudge->type()->name,
                'errors' => $e->validationErrors,
            ]);

            return false;
        }
    }

    private function isTransient(Throwable $e): bool
    {
        if (! $e instanceof TransportExceptionInterface) {
            return false;
        }

        if (! $e instanceof HttpTransportException) {
            return true;
        }

        try {
            $status = $e->getResponse()->getStatusCode();
        } catch (Throwable) {
            return true;
        }

        return $status === 0 || $status >= 500 || $status === self::HTTP_TOO_MANY_REQUESTS;
    }

    private function describe(Throwable $e): string
    {
        if ($e instanceof HttpTransportException) {
            try {
                return sprintf('HTTP %d', $e->getResponse()->getStatusCode());
            } catch (Throwable) {
                return 'HTTP transport error';
            }
        }

        return $e::class;
    }
}
