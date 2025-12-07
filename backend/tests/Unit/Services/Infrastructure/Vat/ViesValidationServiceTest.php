<?php

namespace Tests\Unit\Services\Infrastructure\Vat;

use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Psr\Log\LoggerInterface;
use Mockery;
use Tests\TestCase;

class ViesValidationServiceTest extends TestCase
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private ViesValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = Mockery::mock(HttpClient::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('info')->byDefault();
        $this->service = new ViesValidationService($this->httpClient, $this->logger);
    }

    public function testValidVatNumberReturnsSuccessResponse(): void
    {
        $vatNumber = 'IE1234567A';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->with(15)
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->with(Mockery::any(), [
                'countryCode' => 'IE',
                'vatNumber' => '1234567A',
            ])
            ->once()
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(true);

        $response
            ->shouldReceive('status')
            ->andReturn(200);

        $response
            ->shouldReceive('json')
            ->andReturn([
                'valid' => true,
                'name' => 'Test Company Ltd',
                'address' => '123 Test Street, Dublin',
            ]);

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertTrue($result->valid);
        $this->assertFalse($result->isTransientError);
        $this->assertEquals('Test Company Ltd', $result->businessName);
        $this->assertEquals('123 Test Street, Dublin', $result->businessAddress);
        $this->assertEquals('IE', $result->countryCode);
    }

    public function testInvalidVatNumberReturnsFailureResponse(): void
    {
        $vatNumber = 'IE9999999ZZ';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(true);

        $response
            ->shouldReceive('status')
            ->andReturn(200);

        $response
            ->shouldReceive('json')
            ->andReturn(['valid' => false]);

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertFalse($result->isTransientError);
        $this->assertNull($result->businessName);
    }

    public function testMsMaxConcurrentReqReturnsTransientError(): void
    {
        $vatNumber = 'DE123456789';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(true);

        $response
            ->shouldReceive('status')
            ->andReturn(200);

        $response
            ->shouldReceive('json')
            ->andReturn([
                'actionSucceed' => false,
                'errorWrappers' => [
                    ['error' => 'MS_MAX_CONCURRENT_REQ']
                ]
            ]);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('VIES API returned error', Mockery::any());

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertTrue($result->isTransientError);
        $this->assertNotNull($result->errorMessage);
    }

    public function testMsUnavailableReturnsTransientError(): void
    {
        $vatNumber = 'FR12345678901';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(true);

        $response
            ->shouldReceive('status')
            ->andReturn(200);

        $response
            ->shouldReceive('json')
            ->andReturn([
                'actionSucceed' => false,
                'errorWrappers' => [
                    ['error' => 'MS_UNAVAILABLE']
                ]
            ]);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertTrue($result->isTransientError);
    }

    public function testInvalidInputReturnsNonTransientError(): void
    {
        $vatNumber = 'XX12345678';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(true);

        $response
            ->shouldReceive('status')
            ->andReturn(200);

        $response
            ->shouldReceive('json')
            ->andReturn([
                'actionSucceed' => false,
                'errorWrappers' => [
                    ['error' => 'INVALID_INPUT']
                ]
            ]);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertFalse($result->isTransientError);
    }

    public function testHttpErrorReturnsTransientError(): void
    {
        $vatNumber = 'DE123456789';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andReturn($response);

        $response
            ->shouldReceive('successful')
            ->andReturn(false);

        $response
            ->shouldReceive('status')
            ->andReturn(503);

        $response
            ->shouldReceive('body')
            ->andReturn('Service Unavailable');

        $response
            ->shouldReceive('json')
            ->andReturn([]);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('VIES HTTP error response', Mockery::any());

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertTrue($result->isTransientError);
    }

    public function testConnectionExceptionReturnsTransientError(): void
    {
        $vatNumber = 'FR12345678901';

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andThrow(new ConnectionException('Connection timeout'));

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('VIES connection error', Mockery::any());

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertTrue($result->isTransientError);
        $this->assertEquals('FR', $result->countryCode);
    }

    public function testUnexpectedExceptionReturnsTransientError(): void
    {
        $vatNumber = 'ES12345678';

        $this->httpClient
            ->shouldReceive('timeout')
            ->andReturnSelf();

        $this->httpClient
            ->shouldReceive('post')
            ->andThrow(new \RuntimeException('Unexpected error'));

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('VIES validation exception', Mockery::any());

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertTrue($result->isTransientError);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
