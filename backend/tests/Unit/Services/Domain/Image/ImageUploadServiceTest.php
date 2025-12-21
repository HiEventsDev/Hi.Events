<?php

namespace Tests\Unit\Services\Domain\Image;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Domain\Image\ImageUploadService;
use HiEvents\Services\Infrastructure\Image\DTO\ImageMetadataDTO;
use HiEvents\Services\Infrastructure\Image\DTO\ImageStorageResponseDTO;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;
use HiEvents\Services\Infrastructure\Image\ImageMetadataService;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Illuminate\Http\UploadedFile;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private ImageStorageService $imageStorageService;
    private ImageRepositoryInterface $imageRepository;
    private ImageMetadataService $imageMetadataService;
    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageStorageService = m::mock(ImageStorageService::class);
        $this->imageRepository = m::mock(ImageRepositoryInterface::class);
        $this->imageMetadataService = m::mock(ImageMetadataService::class);

        $this->service = new ImageUploadService(
            $this->imageStorageService,
            $this->imageRepository,
            $this->imageMetadataService
        );
    }

    public function testUploadSuccessfullyCreatesImageRecordWithMetadata(): void
    {
        $uploadedFile = m::mock(UploadedFile::class);
        $storedImage = new ImageStorageResponseDTO(
            filename: 'foo.jpg',
            disk: 'public',
            path: 'images/foo.jpg',
            size: 123456,
            mime_type: 'image/jpeg'
        );
        $metadata = new ImageMetadataDTO(
            width: 800,
            height: 600,
            avg_colour: '#ff5500',
            lqip_base64: 'data:image/webp;base64,abc123',
        );
        $imageDomainObject = m::mock(ImageDomainObject::class);
        $accountId = 123;

        $this->imageStorageService
            ->shouldReceive('store')
            ->once()
            ->with($uploadedFile, 'profile')
            ->andReturn($storedImage);

        $this->imageMetadataService
            ->shouldReceive('extractMetadata')
            ->once()
            ->with($uploadedFile)
            ->andReturn($metadata);

        $this->imageRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'account_id' => $accountId,
                'entity_id' => 1,
                'entity_type' => 'user',
                'type' => 'profile',
                'filename' => 'foo.jpg',
                'disk' => 'public',
                'path' => 'images/foo.jpg',
                'size' => 123456,
                'mime_type' => 'image/jpeg',
                'width' => 800,
                'height' => 600,
                'avg_colour' => '#ff5500',
                'lqip_base64' => 'data:image/webp;base64,abc123',
            ])
            ->andReturn($imageDomainObject);

        $result = $this->service->upload($uploadedFile, 1, 'user', 'profile', $accountId);

        $this->assertSame($imageDomainObject, $result);
    }

    public function testUploadSuccessfullyCreatesImageRecordWithoutMetadata(): void
    {
        $uploadedFile = m::mock(UploadedFile::class);
        $storedImage = new ImageStorageResponseDTO(
            filename: 'foo.jpg',
            disk: 'public',
            path: 'images/foo.jpg',
            size: 123456,
            mime_type: 'image/jpeg'
        );
        $imageDomainObject = m::mock(ImageDomainObject::class);
        $accountId = 123;

        $this->imageStorageService
            ->shouldReceive('store')
            ->once()
            ->with($uploadedFile, 'profile')
            ->andReturn($storedImage);

        $this->imageMetadataService
            ->shouldReceive('extractMetadata')
            ->once()
            ->with($uploadedFile)
            ->andReturn(null);

        $this->imageRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'account_id' => $accountId,
                'entity_id' => 1,
                'entity_type' => 'user',
                'type' => 'profile',
                'filename' => 'foo.jpg',
                'disk' => 'public',
                'path' => 'images/foo.jpg',
                'size' => 123456,
                'mime_type' => 'image/jpeg',
            ])
            ->andReturn($imageDomainObject);

        $result = $this->service->upload($uploadedFile, 1, 'user', 'profile', $accountId);

        $this->assertSame($imageDomainObject, $result);
    }

    public function testUploadThrowsExceptionIfStorageFails(): void
    {
        $this->expectException(CouldNotUploadImageException::class);

        $uploadedFile = m::mock(UploadedFile::class);
        $accountId = 123;

        $this->imageStorageService
            ->shouldReceive('store')
            ->once()
            ->with($uploadedFile, 'profile')
            ->andThrow(new CouldNotUploadImageException('Failed to store image'));

        $this->service->upload($uploadedFile, 1, 'user', 'profile', $accountId);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
