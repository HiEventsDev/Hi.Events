<?php

namespace HiEvents\Services\Infrastructure\Session;

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class CheckoutSessionManagementService
{
    private const SESSION_IDENTIFIER = 'session_identifier';

    private ?string $sessionId = null;

    public function __construct(
        private readonly Request    $request,
        private readonly Repository $config,
    )
    {
    }

    /**
     * Get the session ID from query param, cookie, or generate a new one.
     */
    public function getSessionId(): string
    {
        if ($this->sessionId) {
            return $this->sessionId;
        }

        $this->sessionId = $this->request->query(self::SESSION_IDENTIFIER)
            ?? $this->request->cookie(self::SESSION_IDENTIFIER)
            ?? $this->createSessionId();

        return $this->sessionId;
    }

    public function verifySession(string $identifier): bool
    {
        return $this->getSessionId() === $identifier;
    }

    public function getSessionCookie(): SymfonyCookie
    {
        return Cookie::make(
            name: self::SESSION_IDENTIFIER,
            value: $this->getSessionId(),
            domain: $this->config->get('session.domain') ?? '.' . $this->request->getHost(),
            secure: true,
            sameSite: 'None',
        );
    }

    private function createSessionId(): string
    {
        return sha1(Str::uuid() . Str::random(40));
    }
}
