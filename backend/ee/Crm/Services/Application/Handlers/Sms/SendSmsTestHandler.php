<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Infrastructure\Phone\PhoneNumberNormalizer;
use Illuminate\Config\Repository;

class SendSmsTestHandler
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly Repository $config,
    ) {}

    /**
     * @throws SmsProviderException
     */
    public function handle(int $organizerId, string $phone): void
    {
        $connection = $this->connectionService->findForOrganizer($organizerId)
            ?? throw new SmsProviderException(__('Connect Twilio before sending a test message.'), isPermanent: true);
        $to = (string) $this->phoneNumberNormalizer->toMobileE164($phone);

        if (! in_array($this->phoneNumberNormalizer->countryOf($to), $this->connectionService->enabledCountries($connection), true)) {
            throw new SmsProviderException(__('Text messages aren\'t switched on for this number\'s country.'), isPermanent: true);
        }

        $organizerName = (string) $this->organizerRepository->findFirstWhere(['id' => $organizerId])?->getName();

        $this->connectionService->send(
            $connection,
            $to,
            trim($organizerName).': '.__('This is a test message from :app_name. Your text messages are set up.', ['app_name' => $this->config->get('app.name')]),
            trackDelivery: false,
        );
    }
}
