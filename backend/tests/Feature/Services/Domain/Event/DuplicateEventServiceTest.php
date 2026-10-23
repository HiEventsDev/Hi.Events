<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Event;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicateEventServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->actingAs($user);
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;
        $now = now()->toDateTimeString();

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Duplicate Organizer',
            'email' => 'duplicate-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('organizer_settings')->insert([
            'organizer_id' => $this->organizerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_duplicating_excludes_occurrences_that_are_past_even_on_the_same_utc_day(): void
    {
        $eventId = $this->insertRecurringEvent();

        $pastTodayStart = now()->utc()->startOfDay()->addSecond();
        $futureStart = now()->utc()->addDay();

        $this->insertOccurrence($eventId, $pastTodayStart->toDateTimeString());
        $this->insertOccurrence($eventId, $futureStart->toDateTimeString());

        $newEvent = $this->duplicate($eventId);

        $clonedStartDates = DB::table('event_occurrences')
            ->where('event_id', $newEvent->getId())
            ->whereNull('deleted_at')
            ->pluck('start_date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $this->assertContains($futureStart->toDateTimeString(), $clonedStartDates, 'The future occurrence must be cloned');
        $this->assertNotContains($pastTodayStart->toDateTimeString(), $clonedStartDates, 'A same-UTC-day but already-past occurrence must not be cloned');
    }

    public function test_product_and_price_sale_dates_keep_the_same_instant_in_a_non_utc_event(): void
    {
        $eventId = $this->insertEvent(timezone: 'America/New_York');
        $categoryId = $this->insertCategory($eventId, 'Tickets', 1);
        $productId = $this->insertProduct($eventId, $categoryId, [
            'sale_start_date' => '2030-03-01 14:00:00',
            'sale_end_date' => '2030-03-10 22:30:00',
        ]);
        $this->insertPrice($productId, [
            'sale_start_date' => '2030-03-02 09:00:00',
            'sale_end_date' => '2030-03-09 18:00:00',
        ]);

        $newEvent = $this->duplicate($eventId, duplicateProducts: true);

        $newProduct = DB::table('products')->where('event_id', $newEvent->getId())->first();
        $this->assertSame('2030-03-01 14:00:00', $newProduct->sale_start_date);
        $this->assertSame('2030-03-10 22:30:00', $newProduct->sale_end_date);

        $newPrice = DB::table('product_prices')->where('product_id', $newProduct->id)->first();
        $this->assertSame('2030-03-02 09:00:00', $newPrice->sale_start_date);
        $this->assertSame('2030-03-09 18:00:00', $newPrice->sale_end_date);
    }

    public function test_promo_code_expiry_keeps_the_same_instant_in_a_non_utc_event(): void
    {
        $eventId = $this->insertEvent(timezone: 'America/New_York');
        DB::table('promo_codes')->insert([
            'event_id' => $eventId,
            'code' => 'earlybird',
            'discount' => 10,
            'discount_type' => 'PERCENTAGE',
            'expiry_date' => '2030-03-01 14:00:00',
            'applicable_product_ids' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newEvent = $this->duplicate($eventId, duplicatePromoCodes: true);

        $this->assertSame(
            '2030-03-01 14:00:00',
            Carbon::parse(DB::table('promo_codes')->where('event_id', $newEvent->getId())->value('expiry_date'))->utc()->toDateTimeString(),
        );
    }

    public function test_product_categories_keep_their_order(): void
    {
        $eventId = $this->insertEvent();
        $this->insertCategory($eventId, 'Second', 2);
        $this->insertCategory($eventId, 'First', 1);
        $this->insertCategory($eventId, 'Third', 3);

        $newEvent = $this->duplicate($eventId, duplicateProducts: true);

        $this->assertSame(
            ['First', 'Second', 'Third'],
            DB::table('product_categories')
                ->where('event_id', $newEvent->getId())
                ->orderBy('order')
                ->orderBy('id')
                ->pluck('name')
                ->all(),
        );
        $this->assertSame(
            [1, 2, 3],
            DB::table('product_categories')->where('event_id', $newEvent->getId())->orderBy('order')->pluck('order')->all(),
        );
    }

    public function test_questions_keep_their_description_and_order(): void
    {
        $eventId = $this->insertEvent();
        $categoryId = $this->insertCategory($eventId, 'Tickets', 1);
        $productId = $this->insertProduct($eventId, $categoryId);
        $this->insertPrice($productId);

        $this->insertQuestion($eventId, 'Dietary needs', 'PRODUCT', 2, '<p>Tell us about allergies</p>', $productId);
        $this->insertQuestion($eventId, 'Company', 'ORDER', 3, '<p>Where you work</p>');
        $this->insertQuestion($eventId, 'T-shirt size', 'PRODUCT', 1, '<p>Unisex sizes</p>', $productId);

        $newEvent = $this->duplicate($eventId, duplicateProducts: true, duplicateQuestions: true);

        $questions = DB::table('questions')
            ->where('event_id', $newEvent->getId())
            ->orderBy('order')
            ->get(['title', 'description', 'order']);

        $this->assertSame(['T-shirt size', 'Dietary needs', 'Company'], $questions->pluck('title')->all());
        $this->assertSame(
            ['<p>Unisex sizes</p>', '<p>Tell us about allergies</p>', '<p>Where you work</p>'],
            $questions->pluck('description')->all(),
        );
        $this->assertSame([1, 2, 3], $questions->pluck('order')->all());
    }

    public function test_event_email_templates_are_copied(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEmailTemplate($eventId, 'order_confirmation', 'Thanks {{ order.first_name }}', true);
        $this->insertEmailTemplate($eventId, 'attendee_ticket', 'Your ticket', false);
        $this->insertEmailTemplate(null, 'order_confirmation', 'Organizer default', true);

        $newEvent = $this->duplicate($eventId, duplicateEmailTemplates: true);

        $copied = DB::table('email_templates')
            ->where('event_id', $newEvent->getId())
            ->orderBy('template_type')
            ->get();

        $this->assertCount(2, $copied);
        $this->assertSame(['attendee_ticket', 'order_confirmation'], $copied->pluck('template_type')->all());
        $this->assertSame(['Your ticket', 'Thanks {{ order.first_name }}'], $copied->pluck('subject')->all());
        $this->assertSame([false, true], $copied->pluck('is_active')->all());
        $this->assertSame($this->accountId, $copied->first()->account_id);
        $this->assertNull($copied->first()->organizer_id);
        $this->assertSame('{"label":"View order","url_token":"order.url"}', $copied->last()->cta);
    }

    public function test_event_email_templates_are_skipped_when_not_requested(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEmailTemplate($eventId, 'order_confirmation', 'Thanks', true);

        $newEvent = $this->duplicate($eventId, duplicateEmailTemplates: false);

        $this->assertSame(0, DB::table('email_templates')->where('event_id', $newEvent->getId())->count());
    }

    public function test_cover_image_and_ticket_logo_keep_their_metadata(): void
    {
        $eventId = $this->insertEvent();
        $this->insertImage($eventId, 'EVENT_COVER');
        $this->insertImage($eventId, 'TICKET_LOGO');

        $newEvent = $this->duplicate($eventId, duplicateEventCoverImage: true, duplicateTicketLogo: true);

        $images = DB::table('images')
            ->where('entity_type', EventDomainObject::class)
            ->where('entity_id', $newEvent->getId())
            ->orderBy('type')
            ->get();

        $this->assertSame(['EVENT_COVER', 'TICKET_LOGO'], $images->pluck('type')->all());
        foreach ($images as $image) {
            $this->assertSame($this->accountId, $image->account_id);
            $this->assertSame(1600, $image->width);
            $this->assertSame(900, $image->height);
            $this->assertSame('#336699', $image->avg_colour);
            $this->assertSame('data:image/webp;base64,AAAA', $image->lqip_base64);
        }
    }

    public function test_check_in_list_for_a_date_that_is_not_copied_is_skipped(): void
    {
        $eventId = $this->insertRecurringEvent();
        $pastOccurrenceId = $this->insertOccurrence($eventId, now()->utc()->subDays(3)->toDateTimeString());
        $futureOccurrenceId = $this->insertOccurrence($eventId, now()->utc()->addDays(3)->toDateTimeString());
        $categoryId = $this->insertCategory($eventId, 'Tickets', 1);
        $productId = $this->insertProduct($eventId, $categoryId);
        $this->insertPrice($productId);

        $this->insertCheckInList($eventId, 'Past night door', $pastOccurrenceId, $productId);
        $this->insertCheckInList($eventId, 'Next night door', $futureOccurrenceId, $productId);
        $this->insertCheckInList($eventId, 'Every night', null, $productId);

        $newEvent = $this->duplicate($eventId, duplicateProducts: true, duplicateCheckInLists: true, duplicateOccurrences: true);

        $lists = DB::table('check_in_lists')
            ->where('event_id', $newEvent->getId())
            ->where('is_system_default', false)
            ->orderBy('name')
            ->get(['name', 'event_occurrence_id']);

        $this->assertSame(['Every night', 'Next night door'], $lists->pluck('name')->all());
        $this->assertNull($lists->first()->event_occurrence_id);
        $this->assertNotNull($lists->last()->event_occurrence_id);
    }

    private function duplicate(
        int $eventId,
        bool $duplicateProducts = false,
        bool $duplicateQuestions = false,
        bool $duplicateCheckInLists = false,
        bool $duplicateEventCoverImage = false,
        bool $duplicateTicketLogo = false,
        bool $duplicateOccurrences = true,
        bool $duplicateEmailTemplates = false,
        bool $duplicatePromoCodes = false,
    ): EventDomainObject {
        return $this->app->make(DuplicateEventService::class)->duplicateEvent(
            eventId: (string) $eventId,
            accountId: (string) $this->accountId,
            title: 'Duplicated event',
            startDate: now()->utc()->addDays(10)->toDateTimeString(),
            duplicateProducts: $duplicateProducts,
            duplicateQuestions: $duplicateQuestions,
            duplicateSettings: false,
            duplicatePromoCodes: $duplicatePromoCodes,
            duplicateCapacityAssignments: false,
            duplicateCheckInLists: $duplicateCheckInLists,
            duplicateEventCoverImage: $duplicateEventCoverImage,
            duplicateTicketLogo: $duplicateTicketLogo,
            duplicateWebhooks: false,
            duplicateAffiliates: false,
            duplicateOccurrences: $duplicateOccurrences,
            duplicateLineup: false,
            duplicateEmailTemplates: $duplicateEmailTemplates,
        );
    }

    private function insertEvent(string $timezone = 'UTC'): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => 'Source event',
            'status' => 'DRAFT',
            'type' => 'SINGLE',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => $timezone,
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertRecurringEvent(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => 'Recurring source event',
            'status' => 'DRAFT',
            'type' => 'RECURRING',
            'recurrence_rule' => json_encode([
                'range' => ['type' => 'count', 'count' => 2, 'start' => now()->utc()->toDateString()],
                'interval' => 1,
                'frequency' => 'weekly',
                'days_of_week' => ['monday'],
                'times_of_day' => ['19:00'],
            ]),
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertOccurrence(int $eventId, string $startDate): int
    {
        $now = now()->toDateTimeString();

        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate,
            'end_date' => null,
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertCategory(int $eventId, string $name, int $order): int
    {
        return DB::table('product_categories')->insertGetId([
            'event_id' => $eventId,
            'name' => $name,
            'order' => $order,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(int $eventId, int $categoryId, array $attributes = []): int
    {
        return DB::table('products')->insertGetId(array_merge([
            'title' => 'General admission',
            'event_id' => $eventId,
            'product_category_id' => $categoryId,
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function insertPrice(int $productId, array $attributes = []): int
    {
        return DB::table('product_prices')->insertGetId(array_merge([
            'product_id' => $productId,
            'price' => 10.00,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function insertQuestion(
        int $eventId,
        string $title,
        string $belongsTo,
        int $order,
        string $description,
        ?int $productId = null,
    ): void {
        $questionId = DB::table('questions')->insertGetId([
            'event_id' => $eventId,
            'title' => $title,
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => $belongsTo,
            'required' => false,
            'is_hidden' => false,
            'order' => $order,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($productId !== null) {
            DB::table('product_questions')->insert([
                'product_id' => $productId,
                'question_id' => $questionId,
            ]);
        }
    }

    private function insertEmailTemplate(?int $eventId, string $type, string $subject, bool $isActive): void
    {
        DB::table('email_templates')->insert([
            'account_id' => $this->accountId,
            'organizer_id' => $eventId === null ? $this->organizerId : null,
            'event_id' => $eventId,
            'template_type' => $type,
            'subject' => $subject,
            'body' => '<p>Body for '.$type.'</p>',
            'cta' => json_encode(['label' => 'View order', 'url_token' => 'order.url']),
            'engine' => 'liquid',
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertImage(int $eventId, string $type): void
    {
        DB::table('images')->insert([
            'account_id' => $this->accountId,
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => $type,
            'filename' => 'cover.webp',
            'disk' => 'public',
            'path' => 'event_images/cover.webp',
            'size' => 1234,
            'mime_type' => 'image/webp',
            'width' => 1600,
            'height' => 900,
            'avg_colour' => '#336699',
            'lqip_base64' => 'data:image/webp;base64,AAAA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCheckInList(int $eventId, string $name, ?int $occurrenceId, int $productId): void
    {
        $listId = DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'event_id' => $eventId,
            'event_occurrence_id' => $occurrenceId,
            'name' => $name,
            'is_system_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_check_in_lists')->insert([
            'product_id' => $productId,
            'check_in_list_id' => $listId,
        ]);
    }
}
