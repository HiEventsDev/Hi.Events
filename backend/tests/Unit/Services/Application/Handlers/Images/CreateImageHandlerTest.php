<?php

namespace Tests\Unit\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\CreateImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO;
use HiEvents\Services\Domain\Image\DTO\ImageReferenceDTO;
use HiEvents\Services\Domain\Image\ImageInUseService;
use HiEvents\Services\Domain\Image\ImageUploadService;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CreateImageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ImageUploadService $imageUploadService;

    private EventRepositoryInterface $eventRepository;

    private ImageRepositoryInterface $imageRepository;

    private ImageStorageService $imageStorageService;

    private ImageInUseService $imageInUseService;

    private CreateImageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageUploadService = m::mock(ImageUploadService::class);
        $organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->imageRepository = m::mock(ImageRepositoryInterface::class);
        $this->imageStorageService = m::mock(ImageStorageService::class);
        $this->imageInUseService = m::mock(ImageInUseService::class);

        $this->handler = new CreateImageHandler(
            $this->imageUploadService,
            $organizerRepository,
            $this->eventRepository,
            $this->imageRepository,
            $this->imageStorageService,
            $this->imageInUseService,
        );
    }

    public function test_handle_successfully_creates_image(): void
    {
        $uploadedFile = m::mock(UploadedFile::class);
        $imageDomainObject = m::mock(ImageDomainObject::class);
        $accountId = 123;

        $dto = new CreateImageDTO(
            userId: 42,
            accountId: $accountId,
            image: $uploadedFile
        );

        $this->imageUploadService
            ->shouldReceive('upload')
            ->once()
            ->withArgs([
                $uploadedFile,
                42,
                UserDomainObject::class,
                ImageType::GENERIC->name,
                $accountId,
            ])
            ->andReturn($imageDomainObject);

        $result = $this->handler->handle($dto);

        $this->assertSame($imageDomainObject, $result);
    }

    public function test_replacing_entity_image_deletes_prior_files_when_unreferenced(): void
    {
        $accountId = 123;
        $eventId = 7;

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn($accountId);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $existing = (new ImageDomainObject)
            ->setId(99)
            ->setAccountId($accountId)
            ->setDisk('public')
            ->setPath('event_cover/old.png');

        $where = [
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => ImageType::EVENT_COVER->name,
        ];

        $this->imageRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with($where)
            ->andReturn(new Collection([$existing]));

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with($where);

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->with('event_cover/old.png', $accountId)
            ->andReturn([]);

        $this->imageStorageService
            ->shouldReceive('delete')
            ->once()
            ->with('public', 'event_cover/old.png')
            ->andReturn(true);

        $uploadedFile = m::mock(UploadedFile::class);
        $newImage = m::mock(ImageDomainObject::class);

        $this->imageUploadService
            ->shouldReceive('upload')
            ->once()
            ->withArgs([
                $uploadedFile,
                $eventId,
                EventDomainObject::class,
                ImageType::EVENT_COVER->name,
                $accountId,
            ])
            ->andReturn($newImage);

        $dto = new CreateImageDTO(
            userId: 42,
            accountId: $accountId,
            image: $uploadedFile,
            imageType: ImageType::EVENT_COVER,
            entityId: $eventId,
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($newImage, $result);
    }

    public function test_replacing_entity_image_keeps_prior_file_when_still_referenced(): void
    {
        $accountId = 123;
        $eventId = 7;

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn($accountId);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $existing = (new ImageDomainObject)
            ->setId(99)
            ->setAccountId($accountId)
            ->setDisk('public')
            ->setPath('event_cover/old.png');

        $this->imageRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$existing]));

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once();

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->andReturn([new ImageReferenceDTO(
                entity_type: 'event',
                entity_id: 11,
                entity_name: 'Other event',
                field_label: 'Email footer',
                is_protected: true,
            )]);

        $this->imageStorageService->shouldNotReceive('delete');

        $uploadedFile = m::mock(UploadedFile::class);
        $newImage = m::mock(ImageDomainObject::class);

        $this->imageUploadService
            ->shouldReceive('upload')
            ->once()
            ->andReturn($newImage);

        $dto = new CreateImageDTO(
            userId: 42,
            accountId: $accountId,
            image: $uploadedFile,
            imageType: ImageType::EVENT_COVER,
            entityId: $eventId,
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($newImage, $result);
    }
}
