<?php

namespace Tests\Unit\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\CreateImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO;
use HiEvents\Services\Domain\Image\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CreateImageHandlerTest extends TestCase
{
    private ImageUploadService $imageUploadService;
    private EventRepositoryInterface $eventRepository;
    private ImageRepositoryInterface $imageRepository;
    private CreateImageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageUploadService = m::mock(ImageUploadService::class);
        $organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->imageRepository = m::mock(ImageRepositoryInterface::class);

        $this->handler = new CreateImageHandler(
            $this->imageUploadService,
            $organizerRepository,
            $this->eventRepository,
            $this->imageRepository
        );
    }

    public function testHandleSuccessfullyCreatesImage(): void
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

    public function test_ticket_template_is_in_event_image_types(): void
    {
        $this->assertContains(
            ImageType::TICKET_TEMPLATE,
            ImageType::eventImageTypes(),
            'TICKET_TEMPLATE must be in eventImageTypes() so getEntityType() resolves correctly'
        );
    }

    public function test_ticket_template_minimum_dimensions_are_400_by_200(): void
    {
        $dims = ImageType::getMinimumDimensionsMap(ImageType::TICKET_TEMPLATE);
        $this->assertSame(400, $dims[0]);
        $this->assertSame(200, $dims[1]);
    }

    public function test_ticket_template_deletes_existing_image_on_upload(): void
    {
        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                'entity_id'   => 1,
                'entity_type' => \HiEvents\DomainObjects\EventDomainObject::class,
                'type'        => 'TICKET_TEMPLATE',
            ]);

        $this->imageUploadService
            ->shouldReceive('upload')
            ->once()
            ->andReturn(new \HiEvents\DomainObjects\ImageDomainObject());

        $dto = new \HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO(
            image: $this->createMock(\Illuminate\Http\UploadedFile::class),
            imageType: ImageType::TICKET_TEMPLATE,
            entityId: 1,
            accountId: 1,
            userId: 1,
        );

        $event = \Mockery::mock(\HiEvents\DomainObjects\EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn(1);
        $this->eventRepository->shouldReceive('findById')->with(1)->andReturn($event);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
