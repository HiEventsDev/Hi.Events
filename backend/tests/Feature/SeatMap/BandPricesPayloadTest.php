<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeProductResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeProductCatalogueService;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductLinkDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductsDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapBandProductsHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as ResponseCodes;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class BandPricesPayloadTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const PRICES_PATH = 'data.product_categories.0.products.0.prices.0';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();

        $categoryId = DB::table('product_categories')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Tickets',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->productId = $this->insertProduct();
        DB::table('products')->where('id', $this->productId)->update(['product_category_id' => $categoryId]);
        $this->priceId = $this->insertPrice($this->productId, null, price: 30.00);

        $this->insertEventSeatMap($this->seatMapFixture('theatre'));
        app(UpdateEventSeatMapBandProductsHandler::class)->handle($this->eventId, collect([
            new BandProductsDTO(band_key: 'b_premium', products: collect([new BandProductLinkDTO(product_id: $this->productId, price_adjustment: 1500)])),
            new BandProductsDTO(band_key: 'b_standard', products: collect([new BandProductLinkDTO(product_id: $this->productId, price_adjustment: 0)])),
        ]));
    }

    public function test_band_prices_are_an_object_keyed_by_band(): void
    {
        $this->getJson("/public/events/{$this->eventId}")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath(self::PRICES_PATH.'.price_including_taxes_and_fees', 30)
            ->assertJsonPath(self::PRICES_PATH.'.band_prices', ['b_premium' => 45, 'b_standard' => 30]);

        $this->assertStringContainsString(
            '"band_prices":{"b_premium":45',
            $this->getJson("/public/events/{$this->eventId}")->getContent(),
        );
    }

    public function test_band_prices_apply_a_percentage_promo_after_the_adjustment(): void
    {
        DB::table('promo_codes')->insert([
            'code' => 'half',
            'discount' => 50,
            'discount_type' => 'PERCENTAGE',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/public/events/{$this->eventId}?promo_code=half")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath(self::PRICES_PATH.'.price', 15)
            ->assertJsonPath(self::PRICES_PATH.'.band_prices', ['b_premium' => 22.5, 'b_standard' => 15]);
    }

    public function test_band_prices_start_from_the_per_date_price(): void
    {
        DB::table('product_price_occurrence_overrides')->insert([
            'event_occurrence_id' => $this->occurrenceId,
            'product_price_id' => $this->priceId,
            'price' => 20.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/public/events/{$this->eventId}?event_occurrence_id={$this->occurrenceId}")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath(self::PRICES_PATH.'.price', 20)
            ->assertJsonPath(self::PRICES_PATH.'.band_prices', ['b_premium' => 35, 'b_standard' => 20]);
    }

    public function test_band_prices_include_a_platform_fee_passed_on_to_the_buyer(): void
    {
        $this->passPlatformFeeToBuyer(percentage: 10);

        $response = $this->getJson("/public/events/{$this->eventId}")->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertEqualsWithDelta(33.33, $response->json(self::PRICES_PATH.'.price_including_taxes_and_fees'), 0.001);
        $this->assertEqualsWithDelta(50.0, $response->json(self::PRICES_PATH.'.band_prices.b_premium'), 0.001);
        $this->assertEqualsWithDelta(33.33, $response->json(self::PRICES_PATH.'.band_prices.b_standard'), 0.001);
    }

    public function test_the_public_seat_map_carries_no_prices(): void
    {
        $response = $this->getJson("/public/events/{$this->eventId}/seat-map")->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertArrayNotHasKey('band_prices', $response->json('data'));
        foreach ($response->json('data.band_products') as $band) {
            foreach ($band['products'] as $link) {
                $this->assertSame(['product_id' => $this->productId], $link);
            }
        }
    }

    public function test_the_door_catalogue_prices_bands_without_the_platform_fee(): void
    {
        $this->passPlatformFeeToBuyer(percentage: 10);

        $boxOfficeId = DB::table('box_offices')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'bo_'.uniqid(),
            'name' => 'Front door',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $products = app(BoxOfficeProductCatalogueService::class)->getSellableProducts(
            (new BoxOfficeDomainObject)->setId($boxOfficeId)->setEventId($this->eventId),
            $this->occurrenceId,
        );

        $payload = json_decode(json_encode(
            (new BoxOfficeProductResourcePublic($products->first(fn (ProductDomainObject $product) => $product->getId() === $this->productId)))
                ->toArray(Request::create('/')),
        ), true);

        $this->assertSame(['b_premium' => 45, 'b_standard' => 30], $payload['prices'][0]['band_prices']);
    }

    private function passPlatformFeeToBuyer(int $percentage): void
    {
        Config::set('app.saas_mode_enabled', true);

        $configurationId = DB::table('organizer_configurations')->insertGetId([
            'name' => 'Pass-through',
            'is_system_default' => false,
            'application_fees' => json_encode(['fixed' => 0, 'percentage' => $percentage, 'currency' => 'USD']),
            'bypass_application_fees' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('organizers')->where('id', $this->organizerId)->update(['organizer_configuration_id' => $configurationId]);
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['pass_platform_fee_to_buyer' => true]);
    }
}
