<?php

namespace HiEvents\Services\Infrastructure\Session;

use Illuminate\Http\Request;

class SessionIdentifierService
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getIdentifier(): string
    {
        return sha1($this->getIp() . $this->request->userAgent());
    }

    public function verifyIdentifier(string $identifier): bool
    {
        return $this->getIdentifier() === $identifier;
    }

    private function getIp(): string
    {
        // If the request is coming from a DigitalOcean load balancer, use the connecting IP
        if ($digitalOceanIp = $this->request->server('HTTP_DO_CONNECTING_IP')) {
            return $digitalOceanIp;
        }

        return $this->request->getClientIp();
    }
}

