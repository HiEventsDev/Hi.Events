<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationPreviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\PreviewAutomationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Config\Repository;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendTestAutomationHandler
{
    public function __construct(
        private readonly AutomationPreviewService $previewService,
        private readonly CampaignSendGate $sendGate,
        private readonly MailManager $mailManager,
        private readonly Repository $config,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     * @throws CampaignNotSendableException
     */
    public function handle(PreviewAutomationDTO $dto, string $userEmail): void
    {
        $this->sendGate->assertAccountMaySend($dto->account_id);

        if (trim($dto->subject) === '' || trim(strip_tags($dto->body)) === '') {
            throw new CampaignNotSendableException(__('Add a subject and a message before sending.'));
        }

        $mail = $this->previewService->mail($dto);

        try {
            $this->mailManager
                ->mailer($this->config->get('mail.campaigns.mailer'))
                ->to($userEmail)
                ->sendNow($mail);
        } catch (TransportExceptionInterface) {
            throw new CampaignNotSendableException(__('The test email couldn\'t be sent. Check your mail settings or try again in a moment.'));
        }
    }
}
