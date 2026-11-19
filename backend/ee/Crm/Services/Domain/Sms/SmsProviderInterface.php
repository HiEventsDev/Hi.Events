<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;

interface SmsProviderInterface
{
    /**
     * @return SmsSenderDTO[]
     *
     * @throws SmsProviderException
     */
    public function listSenders(SmsCredentialsDTO $credentials): array;

    /**
     * @return SmsSenderDTO|null the sender named by the credentials' messaging service, when the account has it
     *
     * @throws SmsProviderException
     */
    public function findSender(SmsCredentialsDTO $credentials): ?SmsSenderDTO;

    /**
     * @throws SmsProviderException
     */
    public function configureInbound(SmsCredentialsDTO $credentials, string $inboundUrl): void;

    /**
     * @return string the provider's message id
     *
     * @throws SmsProviderException
     */
    public function send(SmsCredentialsDTO $credentials, string $to, string $body, ?string $statusCallbackUrl): string;

    /**
     * @param  array<string, string>  $params
     */
    public function isValidSignature(string $authToken, string $url, array $params, string $signature): bool;
}
