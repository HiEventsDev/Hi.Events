<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\DomainObjects\Generated\CrmSmsConnectionDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Services\Infrastructure\Url\PublicApiUrl;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;

class SmsConnectionService
{
    public const PROVIDER_TWILIO = 'TWILIO';

    private const PUBLIC_ID_HASH_LENGTH = 24;

    public function __construct(
        private readonly CrmSmsConnectionRepositoryInterface $connectionRepository,
        private readonly SmsProviderInterface $provider,
        private readonly DatabaseManager $databaseManager,
        private readonly Repository $config,
    ) {}

    public function findForOrganizer(int $organizerId): ?CrmSmsConnectionDomainObject
    {
        return $this->connectionRepository->findFirstWhere([CrmSmsConnectionDomainObjectAbstract::ORGANIZER_ID => $organizerId]);
    }

    /**
     * @return string[]
     */
    public function enabledCountries(?CrmSmsConnectionDomainObject $connection): array
    {
        $countries = $connection?->getEnabledCountries();

        return array_values(array_intersect(is_array($countries) ? $countries : [], SmsCountry::valuesArray()));
    }

    /**
     * @return SmsSenderDTO[]
     *
     * @throws SmsProviderException
     */
    public function listSenders(int $organizerId, string $accountSid, ?string $authToken): array
    {
        return $this->provider->listSenders(new SmsCredentialsDTO(
            account_sid: $accountSid,
            auth_token: $this->resolveToken($organizerId, $accountSid, $authToken),
        ));
    }

    /**
     * @throws SmsProviderException
     */
    private function resolveToken(int $organizerId, string $accountSid, ?string $authToken): string
    {
        $existing = $this->findForOrganizer($organizerId);
        $token = $authToken ?? ($existing?->getAccountSid() === $accountSid ? $existing->getAuthToken() : null);

        if ($token === null) {
            throw new SmsProviderException(__('Enter the Auth Token for this Twilio account.'), isPermanent: true);
        }

        return $token;
    }

    /**
     * @param  string[]  $enabledCountries
     *
     * @throws SmsProviderException
     */
    public function save(int $accountId, int $organizerId, string $accountSid, ?string $authToken, string $messagingServiceSid, array $enabledCountries): CrmSmsConnectionDomainObject
    {
        $existing = $this->findForOrganizer($organizerId);
        $token = $this->resolveToken($organizerId, $accountSid, $authToken);

        $credentials = new SmsCredentialsDTO(account_sid: $accountSid, auth_token: $token, messaging_service_sid: $messagingServiceSid);

        if ($this->provider->findSender($credentials) === null) {
            throw new SmsProviderException(__('That Messaging Service wasn\'t found in this Twilio account.'), isPermanent: true);
        }

        $usedElsewhere = $this->connectionRepository
            ->findWhere([CrmSmsConnectionDomainObjectAbstract::MESSAGING_SERVICE_SID => $messagingServiceSid])
            ->contains(static fn (CrmSmsConnectionDomainObject $connection) => $connection->getOrganizerId() !== $organizerId);

        if ($usedElsewhere) {
            throw $this->usedByAnotherOrganizer();
        }

        $publicId = $existing?->getPublicId() ?? $this->publicIdFor($organizerId);
        $this->provider->configureInbound($credentials, $this->inboundUrl($publicId));

        $attributes = [
            CrmSmsConnectionDomainObjectAbstract::PROVIDER => self::PROVIDER_TWILIO,
            CrmSmsConnectionDomainObjectAbstract::ACCOUNT_SID => $accountSid,
            CrmSmsConnectionDomainObjectAbstract::AUTH_TOKEN => $token,
            CrmSmsConnectionDomainObjectAbstract::MESSAGING_SERVICE_SID => $messagingServiceSid,
            CrmSmsConnectionDomainObjectAbstract::ENABLED_COUNTRIES => array_values(array_unique($enabledCountries)),
        ];

        try {
            return $this->databaseManager->transaction(fn (): CrmSmsConnectionDomainObject => $existing === null
                ? $this->connectionRepository->create([
                    ...$attributes,
                    CrmSmsConnectionDomainObjectAbstract::ACCOUNT_ID => $accountId,
                    CrmSmsConnectionDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                    CrmSmsConnectionDomainObjectAbstract::PUBLIC_ID => $publicId,
                ])
                : $this->connectionRepository->updateFromArray($existing->getId(), $attributes));
        } catch (UniqueConstraintViolationException) {
            $savedMeanwhile = $this->findForOrganizer($organizerId);

            if ($savedMeanwhile?->getMessagingServiceSid() !== $messagingServiceSid) {
                throw $this->usedByAnotherOrganizer();
            }

            return $this->connectionRepository->updateFromArray($savedMeanwhile->getId(), $attributes);
        }
    }

    private function usedByAnotherOrganizer(): SmsProviderException
    {
        return new SmsProviderException(__('This Messaging Service is already connected to another organizer. Create a separate Messaging Service in Twilio for each organizer.'), isPermanent: true);
    }

    private function publicIdFor(int $organizerId): string
    {
        return 'smsc_'.substr(hash_hmac('sha256', 'crm-sms-connection|'.$organizerId, (string) $this->config->get('app.key')), 0, self::PUBLIC_ID_HASH_LENGTH);
    }

    public function disconnect(int $organizerId): void
    {
        $this->connectionRepository->deleteWhere([CrmSmsConnectionDomainObjectAbstract::ORGANIZER_ID => $organizerId]);
    }

    /**
     * @throws SmsProviderException
     */
    public function send(CrmSmsConnectionDomainObject $connection, string $to, string $body, bool $trackDelivery): string
    {
        return $this->provider->send(
            $this->credentials($connection),
            $to,
            $body,
            $trackDelivery ? $this->statusUrl($connection->getPublicId()) : null,
        );
    }

    public function credentials(CrmSmsConnectionDomainObject $connection): SmsCredentialsDTO
    {
        return new SmsCredentialsDTO(
            account_sid: $connection->getAccountSid(),
            auth_token: $connection->getAuthToken(),
            messaging_service_sid: $connection->getMessagingServiceSid(),
        );
    }

    public function inboundUrl(string $publicId): string
    {
        return (new PublicApiUrl($this->config))->to('public/webhooks/sms/twilio/'.$publicId.'/inbound');
    }

    public function statusUrl(string $publicId): string
    {
        return (new PublicApiUrl($this->config))->to('public/webhooks/sms/twilio/'.$publicId.'/status');
    }
}
