<?php

namespace Unit\Services\Infrastructure\Session;

use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckoutSessionManagementServiceTest extends TestCase
{
    public function testGetSessionId(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('userAgent')
            ->willReturn('userAgent');

        $request->expects($this->once())
            ->method('input')
            ->with('session_identifier')
            ->willReturn('sessionIdentifier');

        $request->expects($this->once())
            ->method('ip')
            ->willReturn('ip');

        $service = new CheckoutSessionManagementService($request);

        $this->assertEquals(sha1('userAgent' . 'ip' . 'sessionIdentifier'), $service->getSessionId());
    }

    public function testVerifySession(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('userAgent')
            ->willReturn('userAgent');

        $request->expects($this->once())
            ->method('input')
            ->with('session_identifier')
            ->willReturn('sessionIdentifier');

        $request->expects($this->once())
            ->method('ip')
            ->willReturn('ip');

        $service = new CheckoutSessionManagementService($request);

        $this->assertTrue($service->verifySession(sha1('userAgent' . 'ip' . 'sessionIdentifier')));
    }
}
