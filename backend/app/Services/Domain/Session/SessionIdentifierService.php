<?php

namespace HiEvents\Services\Domain\Session;

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
        return sha1($this->request->getClientIp() . $this->request->userAgent());
    }

    public function verifyIdentifier(string $identifier): bool
    {
        return $this->getIdentifier() === $identifier;
    }
}

