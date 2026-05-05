<?php

namespace Tests\Unit\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\DeleteImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\DeleteImageDTO;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class DeleteImageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ImageRepositoryInterface $imageRepository;

    private ImageStorageService $imageStorageService;

    private DeleteImageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageRepository = m::mock(ImageRepositoryInterface::class);
        $this->imageStorageService = m::mock(ImageStorageService::class);

        $this->handler = new DeleteImageHandler(
            $this->imageRepository,
            $this->imageStorageService,
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

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
    }

    public function test_deletes_record_and_file(): void
    {
        $image = (new ImageDomainObject)
            ->setId(7)
            ->setAccountId(99)
            ->setDisk('public')
            ->setPath('event_cover/banner.png');

        $this->imageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 7, 'account_id' => 99])
            ->andReturn($image);

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

        $this->imageRepository
            ->shouldReceive('deleteWhere')
            ->once();

        $this->imageStorageService->shouldNotReceive('delete');

        $this->handler->handle(new DeleteImageDTO(imageId: 7, userId: 1, accountId: 99));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
