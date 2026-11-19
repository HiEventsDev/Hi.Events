<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

class TwilioRequestSignature
{
    /**
     * @param  array<string, string>  $params
     */
    public function sign(string $authToken, string $url, array $params): string
    {
        ksort($params);
        $payload = $url;

        foreach ($params as $key => $value) {
            $payload .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $payload, $authToken, true));
    }

    /**
     * @param  array<string, string>  $params
     */
    public function isValid(string $authToken, string $url, array $params, string $signature): bool
    {
        return hash_equals($this->sign($authToken, $url, $params), $signature);
    }
}
