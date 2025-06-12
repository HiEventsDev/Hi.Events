<?php

namespace Tests\Unit\Services\Infrastructure\Session;

use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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

        $configMock = $this->mock(Repository::class);

        $service = new CheckoutSessionManagementService($request, $configMock);

        $this->assertEquals('existingSessionId', $service->getSessionId());
    }

    public function testVerifySession(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('cookie')
            ->with('session_identifier')
            ->willReturn('existingSessionId');

        $configMock = $this->mock(Repository::class);

        $service = new CheckoutSessionManagementService($request, $configMock);

        $this->assertTrue($service->verifySession('existingSessionId'));
    }

    public function testGetSessionCookie(): void
    {
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('cookie')
            ->with('session_identifier')
            ->willReturn('existingSessionId');

        $configMock = $this->mock(Repository::class)
            ->shouldReceive('get')
            ->with('session.domain')
            ->andReturnNull()
            ->getMock();

        $service = new CheckoutSessionManagementService($request, $configMock);

        $cookie = $service->getSessionCookie();

        $this->assertEquals('session_identifier', $cookie->getName());
        $this->assertEquals('existingSessionId', $cookie->getValue());
        $this->assertTrue($cookie->isSecure());
        $this->assertEquals('none', $cookie->getSameSite());
    }
}
