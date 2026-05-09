<?php

namespace Tests\Unit\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ImageInUseException;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\DeleteImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\DeleteImageDTO;
use HiEvents\Services\Domain\Image\DTO\ImageReferenceDTO;
use HiEvents\Services\Domain\Image\ImageInUseService;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use Tests\TestCase;

class DeleteImageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ImageRepositoryInterface $imageRepository;

    private ImageStorageService $imageStorageService;

    private ImageInUseService $imageInUseService;

    private DeleteImageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageRepository = m::mock(ImageRepositoryInterface::class);
        $this->imageStorageService = m::mock(ImageStorageService::class);
        $this->imageInUseService = m::mock(ImageInUseService::class);

        $this->handler = new DeleteImageHandler(
            $this->imageRepository,
            $this->imageStorageService,
            $this->imageInUseService,
        );
    }

    public function test_throws_when_image_not_found_for_account(): void
    {
        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 7, 'account_id' => 99])
            ->andReturn(null);

        $this->imageRepository->shouldNotReceive('deleteWhere');
        $this->imageStorageService->shouldNotReceive('delete');
        $this->imageInUseService->shouldNotReceive('findReferences');

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
    }

    public function test_deletes_record_and_file_when_no_references(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk('public')
            ->setPath('event_cover/banner.png');

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($image);

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->with('event_cover/banner.png', 99)
            ->andReturn([]);

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with(['id' => 7, 'account_id' => 99]);

        $this->imageStorageService
            ->shouldReceive('delete')
            ->once()
            ->with('public', 'event_cover/banner.png')
            ->andReturn(true);

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
    }

    public function test_skips_storage_delete_when_disk_or_path_missing(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk(null)
            ->setPath(null);

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($image);

        $this->imageInUseService->shouldNotReceive('findReferences');

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once();

        $this->imageStorageService->shouldNotReceive('delete');

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
    }

    public function test_blocks_when_referenced_by_active_event(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk('public')
            ->setPath('generic/logo.png');

        $protectedRef = new ImageReferenceDTO(
            entity_type: 'event',
            entity_id: 10,
            entity_name: 'Active Event',
            field_label: 'Email footer',
            is_protected: true,
            event_status: 'LIVE',
            event_lifecycle_status: 'UPCOMING',
        );

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($image);

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->andReturn([$protectedRef]);

        $this->imageRepository->shouldNotReceive('deleteWhere');
        $this->imageStorageService->shouldNotReceive('delete');

        $caught = null;
        try {
            $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
        } catch (ImageInUseException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(ImageInUseException::class, $caught);
        $this->assertTrue($caught->hasProtectedReferences);
    }

    public function test_requires_confirm_for_non_protected_references(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk('public')
            ->setPath('generic/logo.png');

        $endedRef = new ImageReferenceDTO(
            entity_type: 'event',
            entity_id: 10,
            entity_name: 'Past Event',
            field_label: 'Event description',
            is_protected: false,
            event_status: 'LIVE',
            event_lifecycle_status: 'ENDED',
        );

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($image);

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->andReturn([$endedRef]);

        $this->imageRepository->shouldNotReceive('deleteWhere');

        $caught = null;
        try {
            $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99, confirm: false));
        } catch (ImageInUseException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(ImageInUseException::class, $caught);
        $this->assertFalse($caught->hasProtectedReferences);
    }

    public function test_proceeds_with_confirm_for_non_protected_references(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk('public')
            ->setPath('generic/logo.png');

        $endedRef = new ImageReferenceDTO(
            entity_type: 'event',
            entity_id: 10,
            entity_name: 'Past Event',
            field_label: 'Event description',
            is_protected: false,
            event_status: 'LIVE',
            event_lifecycle_status: 'ENDED',
        );

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($image);

        $this->imageInUseService
            ->shouldReceive('findReferences')
            ->once()
            ->andReturn([$endedRef]);

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once();

        $this->imageStorageService
            ->shouldReceive('delete')
            ->once()
            ->with('public', 'generic/logo.png')
            ->andReturn(true);

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99, confirm: true));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
