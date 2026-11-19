<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsComposer;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use HiEvents\Services\Infrastructure\Phone\PhoneNumberNormalizer;
use Illuminate\Config\Repository;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendTestCampaignHandler
{
    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignSendGate $sendGate,
        private readonly MailManager $mailManager,
        private readonly Repository $config,
        private readonly CampaignSmsComposer $smsComposer,
        private readonly SmsConnectionService $smsConnectionService,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return string the address the test went to
     *
     * @throws ResourceNotFoundException
     * @throws CampaignNotSendableException
     * @throws CampaignOrganizerDeletedException
     */
    public function handle(int $organizerId, int $campaignId, string $userEmail, CampaignRecipientDTO $userName, ?string $phone = null): string
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);
        $this->sendGate->assertAccountMaySend($campaign->getAccountId());

        if ($campaign->isSms()) {
            return $this->sendSmsTest($campaign, $userName, $phone);
        }

        if (trim((string) $campaign->getSubject()) === '' || trim(strip_tags((string) $campaign->getBody())) === '') {
            throw new CampaignNotSendableException(__('Add a subject and a message before sending.'));
        }

        $mail = $this->mailFactory->make($campaign, null, $userName);

        try {
            $this->mailManager
                ->mailer($this->config->get('mail.campaigns.mailer'))
                ->to($userEmail)
                ->sendNow($mail);
        } catch (TransportExceptionInterface) {
            throw new CampaignNotSendableException(__('The test email couldn\'t be sent. Check your mail settings or try again in a moment.'));
        }

        return $userEmail;
    }

    /**
     * @throws CampaignNotSendableException
     * @throws CampaignOrganizerDeletedException
     */
    private function sendSmsTest(CrmCampaignDomainObject $campaign, CampaignRecipientDTO $userName, ?string $phone): string
    {
        $to = $this->phoneNumberNormalizer->toMobileE164($phone);
        $connection = $this->smsConnectionService->findForOrganizer($campaign->getOrganizerId());
        $country = SmsCountry::tryFrom((string) $this->phoneNumberNormalizer->countryOf($to));

        if (! $this->featureFlagService->isEnabled(FeatureFlag::CRM_SMS, $campaign->getAccountId())) {
            throw new CampaignNotSendableException(__('Text messages aren\'t available for this account.'));
        }

        if ($to === null) {
            throw new CampaignNotSendableException(__('Enter the mobile number to send the test to.'));
        }

        if (trim((string) $campaign->getBody()) === '') {
            throw new CampaignNotSendableException(__('Add a message before sending.'));
        }

        if ($connection === null || $country === null || ! in_array($country->value, $this->smsConnectionService->enabledCountries($connection), true)) {
            throw new CampaignNotSendableException(__('Text messages aren\'t switched on for this number\'s country.'));
        }

        try {
            $this->smsConnectionService->send($connection, $to, $this->smsComposer->compose($campaign, null, $userName, $country), trackDelivery: false);
        } catch (SmsProviderException $exception) {
            throw new CampaignNotSendableException($exception->getMessage());
        }

        return $to;
    }
}
