<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Demo;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\Enums\TaxType;
use HiEvents\Helper\DateHelper;
use HiEvents\Services\Application\Handlers\Affiliate\CreateAffiliateHandler;
use HiEvents\Services\Application\Handlers\CapacityAssignment\CreateCapacityAssignmentHandler;
use HiEvents\Services\Application\Handlers\CheckInList\CreateCheckInListHandler;
use HiEvents\Services\Application\Handlers\Event\CreateEventHandler;
use HiEvents\Services\Application\Handlers\Event\CreateEventImageHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventImageDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\CreateEventOccurrenceHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\UpsertPriceOverrideHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateProductVisibilityHandler;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PartialUpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\PartialUpdateEventSettingsHandler;
use HiEvents\Services\Application\Handlers\Location\CreateLocationHandler;
use HiEvents\Services\Application\Handlers\Product\CreateProductHandler;
use HiEvents\Services\Application\Handlers\ProductCategory\CreateProductCategoryHandler;
use HiEvents\Services\Application\Handlers\ProductCategory\DTO\UpsertProductCategoryDTO;
use HiEvents\Services\Application\Handlers\ProductCategory\EditProductCategoryHandler;
use HiEvents\Services\Application\Handlers\PromoCode\CreatePromoCodeHandler;
use HiEvents\Services\Application\Handlers\Question\CreateQuestionHandler;
use HiEvents\Services\Application\Handlers\TaxAndFee\CreateTaxOrFeeHandler;
use HiEvents\Services\Application\Handlers\TaxAndFee\DTO\UpsertTaxDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DemoSeedContext
{
    public function __construct(
        public readonly CreateEventHandler $createEvent,
        public readonly CreateLocationHandler $createLocation,
        public readonly CreateProductCategoryHandler $createCategory,
        public readonly EditProductCategoryHandler $editCategory,
        public readonly CreateProductHandler $createProduct,
        public readonly CreateQuestionHandler $createQuestion,
        public readonly CreatePromoCodeHandler $createPromoCode,
        public readonly CreateTaxOrFeeHandler $createTax,
        public readonly CreateCapacityAssignmentHandler $createCapacityAssignment,
        public readonly CreateCheckInListHandler $createCheckInList,
        public readonly CreateAffiliateHandler $createAffiliate,
        public readonly GenerateOccurrencesFromRuleHandler $generateOccurrences,
        public readonly CreateEventOccurrenceHandler $createOccurrence,
        public readonly UpsertPriceOverrideHandler $upsertPriceOverride,
        public readonly UpdateProductVisibilityHandler $updateVisibility,
        private readonly PartialUpdateEventSettingsHandler $updateSettings,
        private readonly CreateEventImageHandler $createImage,
        private readonly DatabaseManager $db,
    ) {}

    public function applySettings(int $accountId, int $eventId, array $settings): void
    {
        $this->updateSettings->handle(new PartialUpdateEventSettingsDTO(
            account_id: $accountId,
            event_id: $eventId,
            settings: $settings,
        ));
    }

    public function uploadCover(int $eventId, int $accountId, string $assetName): void
    {
        $path = resource_path('demo/covers/'.$assetName);

        if (! is_file($path)) {
            throw new RuntimeException('Demo cover image is missing: '.$path);
        }

        $this->createImage->handle(new CreateEventImageDTO(
            eventId: $eventId,
            accountId: $accountId,
            image: new UploadedFile($path, basename($path), mime_content_type($path) ?: 'image/jpeg', null, true),
            imageType: ImageType::EVENT_COVER,
        ));
    }

    public function renameDefaultCategory(int $eventId, string $name, ?string $description, ?string $noProductsMessage = null): int
    {
        $categoryId = $this->defaultCategoryId($eventId);

        $this->editCategory->handle(new UpsertProductCategoryDTO(
            name: $name,
            description: $description,
            is_hidden: false,
            event_id: $eventId,
            no_products_message: $noProductsMessage,
            product_category_id: $categoryId,
        ));

        return $categoryId;
    }

    public function addCategory(int $eventId, string $name, ?string $description, ?string $noProductsMessage = null): int
    {
        return $this->createCategory->handle(new UpsertProductCategoryDTO(
            name: $name,
            description: $description,
            is_hidden: false,
            event_id: $eventId,
            no_products_message: $noProductsMessage,
        ))->getId();
    }

    public function defaultCategoryId(int $eventId): int
    {
        $id = $this->db->table('product_categories')
            ->where('event_id', $eventId)
            ->orderBy('id')
            ->value('id');

        if ($id === null) {
            throw new RuntimeException('Event '.$eventId.' has no default product category.');
        }

        return (int) $id;
    }

    public function taxOrFeeId(
        int $accountId,
        string $name,
        TaxCalculationType $calculationType,
        TaxType $type,
        float $rate,
        ?string $description = null,
    ): int {
        $existing = $this->db->table('taxes_and_fees')
            ->where('account_id', $accountId)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return $this->createTax->handle(new UpsertTaxDTO(
            name: $name,
            description: $description,
            calculation_type: $calculationType,
            type: $type,
            rate: $rate,
            is_active: true,
            is_default: false,
            account_id: $accountId,
        ))->getId();
    }

    public function firstPriceId(int $productId): int
    {
        return (int) $this->db->table('product_prices')
            ->where('product_id', $productId)
            ->orderBy('id')
            ->value('id');
    }

    public function occurrenceCount(int $eventId): int
    {
        return $this->db->table('event_occurrences')->where('event_id', $eventId)->count();
    }

    public function occurrenceIdsExcluding(int $eventId, array $excludedIds): array
    {
        return $this->db->table('event_occurrences')
            ->where('event_id', $eventId)
            ->whereNotIn('id', $excludedIds ?: [0])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function toUtc(string $localDateTime, string $timezone): string
    {
        return DateHelper::convertToUTC($localDateTime, $timezone);
    }
}
