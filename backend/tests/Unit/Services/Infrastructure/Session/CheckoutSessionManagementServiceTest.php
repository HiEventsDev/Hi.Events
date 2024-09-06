<?php

namespace Unit\Services\Infrastructure\Session;

use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckoutSessionManagementServiceTest extends TestCase
{
    public function testGetSessionIdWithExistingCookie(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('cookie')
            ->with('session_identifier')
            ->willReturn('existingSessionId');

        $service = new CheckoutSessionManagementService($request);

        $this->assertEquals('existingSessionId', $service->getSessionId());
    }

    public function testVerifySession(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('cookie')
            ->with('session_identifier')
            ->willReturn('existingSessionId');

        $service = new CheckoutSessionManagementService($request);

        $this->assertTrue($service->verifySession('existingSessionId'));
    }

    public function testGetSessionCookie(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('cookie')
            ->with('session_identifier')
            ->willReturn('existingSessionId');

        $service = new CheckoutSessionManagementService($request);

        $cookie = $service->getSessionCookie();

        $this->assertEquals('session_identifier', $cookie->getName());
        $this->assertEquals('existingSessionId', $cookie->getValue());
        $this->assertTrue($cookie->isSecure());
        $this->assertEquals('none', $cookie->getSameSite());
    }
}
