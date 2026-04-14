<?php

namespace HiEvents\Services\Domain\Email\Ses\EventHandlers;

trait ExtractsRetryHeader
{
    private function getRetryForSesMessageId(array $message): ?string
    {
        $headers = $message['mail']['headers'] ?? [];

        foreach ($headers as $header) {
            if (($header['name'] ?? null) === 'X-HiEvents-Retry-For') {
                return $header['value'] ?? null;
            }
        }

        return null;
    }
}
