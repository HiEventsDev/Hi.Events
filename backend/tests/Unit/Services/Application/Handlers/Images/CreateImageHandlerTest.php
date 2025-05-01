<?php

namespace Tests\Unit\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\Enums\ImageTypes;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Application\Handlers\Images\CreateImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO;
use HiEvents\Services\Domain\Image\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CreateImageHandlerTest extends TestCase
{
    private ImageUploadService $imageUploadService;
    private CreateImageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageUploadService = m::mock(ImageUploadService::class);

        $this->handler = new CreateImageHandler(
            $this->imageUploadService
        );
    }

    public function testHandleSuccessfullyCreatesImage(): void
    {
        $uploadedFile = m::mock(UploadedFile::class);
        $imageDomainObject = m::mock(ImageDomainObject::class);

        $dto = new CreateImageDTO(
            userId: 42,
            image: $uploadedFile
        );

        $this->imageUploadService
            ->shouldReceive('upload')
            ->once()
            ->withArgs([
                $uploadedFile,
                42,
                UserDomainObject::class,
                ImageTypes::GENERIC->name,
            ])
            ->andReturn($imageDomainObject);

        $result = $this->handler->handle($dto);

        $this->assertSame($imageDomainObject, $result);
    }
}
