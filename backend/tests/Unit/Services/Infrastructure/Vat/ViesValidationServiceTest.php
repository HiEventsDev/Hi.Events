<?php

namespace HiEvents\Tests\Unit\Services\Infrastructure\Vat;

use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Request;
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
        $this->service = new ViesValidationService($this->httpClient, $this->logger);
    }

    public function testValidVatNumberReturnsSuccessResponse(): void
    {
        $vatNumber = 'IE1234567A';
        $response = Mockery::mock(Response::class);

        $this->httpClient
            ->shouldReceive('timeout')
            ->with(10)
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
            ->shouldReceive('json')
            ->andReturn([
                'valid' => true,
                'name' => 'Test Company Ltd',
                'address' => '123 Test Street, Dublin',
            ]);

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertTrue($result->valid);
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
            ->shouldReceive('json')
            ->andReturn(['valid' => false]);

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertNull($result->businessName);
    }

    public function testApiErrorLogsWarningAndReturnsFalse(): void
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

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('VIES API request failed', Mockery::on(function ($context) use ($vatNumber) {
                return $context['status'] === 503
                    && $context['vat_number'] === $vatNumber;
            }));

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
    }

    public function testConnectionExceptionLogsErrorAndReturnsFalse(): void
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
            ->with('VIES API connection error', Mockery::on(function ($context) use ($vatNumber) {
                return str_contains($context['error'], 'Connection timeout')
                    && $context['vat_number'] === $vatNumber;
            }));

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
        $this->assertEquals('FR', $result->countryCode);
    }

    public function testUnexpectedExceptionLogsErrorAndReturnsFalse(): void
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
            ->with('VIES validation exception', Mockery::on(function ($context) {
                return str_contains($context['error'], 'Unexpected error');
            }));

        $result = $this->service->validateVatNumber($vatNumber);

        $this->assertFalse($result->valid);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
