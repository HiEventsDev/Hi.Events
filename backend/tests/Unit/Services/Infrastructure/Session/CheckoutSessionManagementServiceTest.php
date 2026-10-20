<?php

namespace Tests\Unit\Services\Infrastructure\Session;

use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CheckoutSessionManagementServiceTest extends TestCase
{
    public function test_get_session_id_with_existing_cookie(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('session_identifier')->andReturnNull();
        $request->shouldReceive('cookie')
            ->once()
            ->with('session_identifier')
            ->andReturn('existingSessionId');

        $configMock = $this->mock(Repository::class);

        $service = new CheckoutSessionManagementService($request, $configMock);

        $this->assertEquals('existingSessionId', $service->getSessionId());
    }

    public function test_verify_session(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('session_identifier')->andReturnNull();
        $request->shouldReceive('cookie')
            ->once()
            ->with('session_identifier')
            ->andReturn('existingSessionId');

        $configMock = $this->mock(Repository::class);

        $service = new CheckoutSessionManagementService($request, $configMock);

        $this->assertTrue($service->verifySession('existingSessionId'));
    }

    public function test_get_session_cookie(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('query')->with('session_identifier')->andReturnNull();
        $request->shouldReceive('cookie')
            ->once()
            ->with('session_identifier')
            ->andReturn('existingSessionId');
        $request->shouldReceive('getHost')->andReturn('example.com');

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
        $this->assertTrue($cookie->isPartitioned());
    }
}
