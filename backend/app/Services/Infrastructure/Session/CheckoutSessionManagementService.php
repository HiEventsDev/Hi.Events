<?php

namespace HiEvents\Services\Infrastructure\Session;

use Illuminate\Http\Request;

class CheckoutSessionManagementService
{
    private const SESSION_IDENTIFIER = 'session_identifier';

    public function __construct(
        private readonly Request $request,
    )
    {
    }

    public function getSessionId(): string
    {
        $userAgent = $this->request->userAgent();
        $ipAddress = $this->getIpAddress();

        return sha1($userAgent . $ipAddress . $this->request->input(self::SESSION_IDENTIFIER));
    }

    public function verifySession(string $identifier): bool
    {
        return $this->getSessionId() === $identifier;
    }

    private function getIpAddress(): string
    {
        if ($digitalOceanIp = $this->request->server('HTTP_DO_CONNECTING_IP')) {
            return $digitalOceanIp;
        }

        return $this->request->ip();
    }
}

