<?php

namespace Tests\Unit\Services\Infrastructure\Image;

use HiEvents\Services\Infrastructure\Image\ImageMetadataService;
use Illuminate\Http\UploadedFile;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImageMetadataServiceTest extends TestCase
{
    private LoggerInterface $logger;
    private ImageMetadataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = m::mock(LoggerInterface::class);
        $this->service = new ImageMetadataService($this->logger);
    }

    public function testExtractMetadataReturnsNullWhenImagickNotAvailable(): void
    {
        if (extension_loaded('imagick')) {
            $this->markTestSkipped('This test requires Imagick to NOT be installed');
        }

        $uploadedFile = m::mock(UploadedFile::class);

        $result = $this->service->extractMetadata($uploadedFile);

        $this->assertNull($result);
    }

    public function testExtractMetadataReturnsMetadataWhenImagickAvailable(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('This test requires Imagick to be installed');
        }

        $testImagePath = $this->createTestImage();
        $uploadedFile = new UploadedFile($testImagePath, 'test.png', 'image/png', null, true);

        $result = $this->service->extractMetadata($uploadedFile);

        $this->assertNotNull($result);
        $this->assertEquals(100, $result->width);
        $this->assertEquals(100, $result->height);
        $this->assertMatchesRegularExpression('/^#[a-f0-9]{6}$/i', $result->avg_colour);
        $this->assertStringStartsWith('data:image/webp;base64,', $result->lqip_base64);

        unlink($testImagePath);
    }

    public function testExtractMetadataLogsWarningOnFailure(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('This test requires Imagick to be installed');
        }

        $uploadedFile = m::mock(UploadedFile::class);
        $uploadedFile->shouldReceive('getRealPath')
            ->once()
            ->andReturn('/nonexistent/path/to/image.png');

        $this->logger->shouldReceive('warning')
            ->once()
            ->with(m::type('string'));

        $result = $this->service->extractMetadata($uploadedFile);

        $this->assertNull($result);
    }

    private function createTestImage(): string
    {
        $imagick = new \Imagick();
        $imagick->newImage(100, 100, '#ff5500');
        $imagick->setImageFormat('png');

        $tempPath = sys_get_temp_dir() . '/test_image_' . uniqid() . '.png';
        $imagick->writeImage($tempPath);
        $imagick->destroy();

        return $tempPath;
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
