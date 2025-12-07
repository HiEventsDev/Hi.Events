<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Generated\ImageDomainObjectAbstract;
use HiEvents\Models\Image;
use HiEvents\Services\Infrastructure\Image\DTO\ImageMetadataDTO;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Imagick;
use Psr\Log\LoggerInterface;
use Throwable;

class BackfillImageMetadataCommand extends Command
{
    protected $signature = 'images:backfill-metadata
                            {--limit=100 : Maximum number of images to process per batch}
                            {--batch-size=50 : Number of images to process before clearing memory}
                            {--dry-run : Show what would be done without actually doing it}
                            {--force : Re-process images that already have metadata}';

    protected $description = 'Backfill image metadata (dimensions, average colour, LQIP) for existing images';

    private const LQIP_MAX_DIMENSION = 16;
    private const LQIP_QUALITY = 60;

    public function __construct(
        private readonly FilesystemManager $filesystemManager,
        private readonly LoggerInterface   $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->isImagickAvailable()) {
            $this->error('Imagick extension is not available. Please install it first.');
            return self::FAILURE;
        }

        $this->info('Starting image metadata backfill...');

        $limit = (int)$this->option('limit');
        $batchSize = (int)$this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $query = Image::query()
            ->whereNull('deleted_at');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull(ImageDomainObjectAbstract::WIDTH)
                    ->orWhereNull(ImageDomainObjectAbstract::HEIGHT)
                    ->orWhereNull(ImageDomainObjectAbstract::AVG_COLOUR)
                    ->orWhereNull(ImageDomainObjectAbstract::LQIP_BASE64);
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No images found that need metadata backfill.');
            return self::SUCCESS;
        }

        $toProcess = min($totalCount, $limit);
        $this->info("Found {$totalCount} images without complete metadata. Processing {$toProcess}...");

        $progressBar = $this->output->createProgressBar($toProcess);
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $processedInBatch = 0;

        $query->take($limit)
            ->orderBy('id')
            ->chunk($batchSize, function ($images) use (
                &$successCount,
                &$errorCount,
                &$skippedCount,
                &$processedInBatch,
                $progressBar,
                $dryRun,
                $batchSize,
            ) {
                foreach ($images as $image) {
                    try {
                        $result = $this->processImage($image, $dryRun);

                        if ($result === 'success') {
                            $successCount++;
                        } elseif ($result === 'skipped') {
                            $skippedCount++;
                        } else {
                            $errorCount++;
                        }
                    } catch (Throwable $e) {
                        $this->newLine();
                        $this->error("Failed to process image #{$image->id}: {$e->getMessage()}");
                        $errorCount++;
                    }

                    $progressBar->advance();
                    $processedInBatch++;

                    if ($processedInBatch >= $batchSize) {
                        gc_collect_cycles();
                        $processedInBatch = 0;
                    }
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Backfill complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $successCount],
                ['Errors', $errorCount],
                ['Skipped', $skippedCount],
                ['Total Processed', $successCount + $errorCount + $skippedCount],
            ]
        );

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processImage(Image $image, bool $dryRun): string
    {
        $disk = $image->disk;
        $path = $image->path;

        if (!$disk || !$path) {
            $this->logger->warning("Image #{$image->id} has no disk or path");
            return 'skipped';
        }

        $filesystem = $this->filesystemManager->disk($disk);

        if (!$filesystem->exists($path)) {
            $this->logger->warning("Image file not found for image #{$image->id}: {$path}");
            return 'skipped';
        }

        if ($dryRun) {
            $this->newLine();
            $this->line("Would process: Image #{$image->id}, Path: {$path}");
            return 'success';
        }

        $tempFile = null;
        $imagick = null;

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_backfill_');
            file_put_contents($tempFile, $filesystem->get($path));

            $imagick = new Imagick($tempFile);

            $metadata = $this->extractMetadata($imagick);

            $image->update([
                ImageDomainObjectAbstract::WIDTH => $metadata->width,
                ImageDomainObjectAbstract::HEIGHT => $metadata->height,
                ImageDomainObjectAbstract::AVG_COLOUR => $metadata->avg_colour,
                ImageDomainObjectAbstract::LQIP_BASE64 => $metadata->lqip_base64,
            ]);

            return 'success';
        } finally {
            if ($imagick !== null) {
                $imagick->clear();
                $imagick->destroy();
            }

            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function extractMetadata(Imagick $imagick): ImageMetadataDTO
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        $avgColour = $this->extractAverageColour($imagick);
        $lqipBase64 = $this->generateLqip($imagick);

        return new ImageMetadataDTO(
            width: $width,
            height: $height,
            avg_colour: $avgColour,
            lqip_base64: $lqipBase64,
        );
    }

    private function extractAverageColour(Imagick $imagick): string
    {
        $clone = clone $imagick;
        $clone->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
        $pixel = $clone->getImagePixelColor(0, 0);
        $rgb = $pixel->getColor();
        $clone->clear();
        $clone->destroy();

        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    private function generateLqip(Imagick $imagick): string
    {
        $clone = clone $imagick;

        $width = $clone->getImageWidth();
        $height = $clone->getImageHeight();

        if ($width > $height) {
            $newWidth = self::LQIP_MAX_DIMENSION;
            $newHeight = (int)round($height * (self::LQIP_MAX_DIMENSION / $width));
        } else {
            $newHeight = self::LQIP_MAX_DIMENSION;
            $newWidth = (int)round($width * (self::LQIP_MAX_DIMENSION / $height));
        }

        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        $clone->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        $clone->setImageFormat('webp');
        $clone->setImageCompressionQuality(self::LQIP_QUALITY);
        $clone->stripImage();

        $blob = $clone->getImageBlob();
        $clone->clear();
        $clone->destroy();

        return 'data:image/webp;base64,' . base64_encode($blob);
    }

    private function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(Imagick::class);
    }
}
