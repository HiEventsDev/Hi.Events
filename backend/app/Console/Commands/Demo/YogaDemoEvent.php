<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Demo;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\AttributesDTO;
use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpdateProductVisibilityDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;

class YogaDemoEvent
{
    public const KEY = 'yoga';

    private const WEEKEND_BASE_PRICE = 32.00;

    public function __construct(
        private readonly DemoSeedContext $ctx,
        private readonly string $timezone,
        private readonly string $currency,
    ) {}

    public function seed(DemoOwner $owner): SeededDemoEvent
    {
        $termStart = CarbonImmutable::now($this->timezone)->next(CarbonImmutable::MONDAY);
        $termEnd = $termStart->addWeeks(18)->next(CarbonImmutable::FRIDAY);

        $location = $this->ctx->createLocation->handle(new UpsertLocationDTO(
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            name: 'Stillroom',
            structured_address: new AddressDTO(
                venue_name: 'Stillroom',
                address_line_1: '22 Blackpitts',
                address_line_2: 'First floor, above the bakery',
                city: 'Dublin',
                state_or_region: 'Co. Dublin',
                zip_or_postal_code: 'D08 R6X3',
                country: 'IE',
            ),
            latitude: 53.3341,
            longitude: -6.2735,
        ));

        $event = $this->ctx->createEvent->handle(new CreateEventDTO(
            title: 'Stillroom — Morning Practice & Weekend Specials',
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            user_id: $owner->user_id,
            description: $this->description(),
            attributes: collect([
                new AttributesDTO(name: 'Room size', value: '18 mats', is_public: true),
                new AttributesDTO(name: 'Weekday classes', value: '06:45 Sunrise Vinyasa · 09:30 Slow Flow & Mobility', is_public: true),
                new AttributesDTO(name: 'Booking', value: 'Per class — no membership or contract', is_public: true),
                new AttributesDTO(name: 'Equipment', value: 'Mats, blocks, straps, bolsters and blankets provided', is_public: true),
                new AttributesDTO(name: 'Access', value: 'First floor, seventeen stairs, no lift', is_public: true),
                new AttributesDTO(name: 'Cancellation', value: 'Free move up to 6 hours before', is_public: true),
                new AttributesDTO(name: 'Internal note', value: 'Second PA needed for the sound bath weekends', is_public: false),
            ]),
            timezone: $this->timezone,
            currency: $this->currency,
            category: EventCategory::WELLNESS,
            event_location: new EventLocationData(type: LocationType::IN_PERSON, location_id: $location->getId()),
            status: EventStatus::LIVE->name,
            type: EventType::RECURRING,
        ));

        $eventId = $event->getId();

        $excludedDates = $this->excludedDates($termStart);

        $this->ctx->generateOccurrences->handle(new GenerateOccurrencesDTO(
            event_id: $eventId,
            recurrence_rule: [
                'frequency' => 'weekly',
                'interval' => 1,
                'days_of_week' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'times_of_day' => [
                    ['time' => '06:45', 'label' => 'Sunrise Vinyasa', 'duration_minutes' => 60],
                    ['time' => '09:30', 'label' => 'Slow Flow & Mobility', 'duration_minutes' => 75],
                ],
                'range' => [
                    'type' => 'until',
                    'start' => $termStart->toDateString(),
                    'until' => $termEnd->toDateString(),
                ],
                'default_capacity' => 18,
                'excluded_dates' => $excludedDates,
            ],
        ));

        $weekendOccurrenceIds = [];
        $weekendPrices = [];

        foreach ($this->weekendSpecials() as $special) {
            $start = $termStart
                ->addWeeks($special['week'])
                ->addDays($special['day_offset'])
                ->setTimeFromTimeString($special['time']);

            $occurrenceId = $this->ctx->createOccurrence->handle(new UpsertEventOccurrenceDTO(
                event_id: $eventId,
                start_date: $this->ctx->toUtc($start->toDateTimeString(), $this->timezone),
                end_date: $this->ctx->toUtc($start->addMinutes($special['minutes'])->toDateTimeString(), $this->timezone),
                capacity: $special['capacity'],
                label: $special['label'],
                show_available_capacity: true,
                is_overridden: true,
            ))->getId();

            $weekendOccurrenceIds[] = $occurrenceId;

            if ($special['price'] !== self::WEEKEND_BASE_PRICE) {
                $weekendPrices[$occurrenceId] = $special['price'];
            }
        }

        $weekdayCategory = $this->ctx->renameDefaultCategory(
            $eventId,
            'Weekday classes',
            'Mon–Fri, 06:45 and 09:30. Pick a date from the calendar — every class is booked individually and there is nothing to cancel afterwards.',
        );
        $weekendCategory = $this->ctx->addCategory($eventId, 'Weekend specials', 'Longer one-off sessions, smaller rooms, usually two teachers. Twelve to eighteen places each and they do go.');
        $extrasCategory = $this->ctx->addCategory($eventId, 'Studio extras', 'Added to any class.');

        $matHire = $this->addon($owner, $eventId, $extrasCategory, 'Mat hire', 'A clean studio mat waiting on your spot. Sanitised between every class. Bring your own if you would rather — most regulars do eventually.', 2.00);
        $coffee = $this->addon($owner, $eventId, $extrasCategory, 'Coffee & pastry from downstairs', 'Ordered ahead and waiting at the bottom of the stairs when you finish. The bakery opens at 07:00, so this is only worth it after the 09:30.', 5.50);

        $dropIn = $this->classProduct($owner, $eventId, $weekdayCategory, 'Drop-in class', 'One class, any weekday morning. Choose the date and time above. No membership, no minimum, nothing renews.', 18.00, 4, [$matHire, $coffee], showRemaining: true);
        $community = $this->classProduct($owner, $eventId, $weekdayCategory, 'Community class', 'The same class for less, no questions asked and nothing to prove. For students, carers, anyone out of work, and anyone for whom the full price twice a week is the reason they stop coming. We keep a few spots in every class for this.', 11.00, 2, [$matHire, $coffee]);

        $firstFree = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $weekdayCategory,
            title: 'First class free',
            type: ProductPriceType::FREE,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 0.0)]),
            max_per_order: 1,
            description: 'New here? Take one on us. One per person, no card details held, and nobody will follow up to ask why you did not come back.',
            min_per_order: 1,
            is_hidden_without_promo_code: true,
            addon_product_ids: [$matHire],
        ))->getId();

        $weekendSpecial = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $weekendCategory,
            title: 'Weekend special',
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: self::WEEKEND_BASE_PRICE)]),
            max_per_order: 3,
            description: 'A place at whichever weekend session you picked above. Price varies by session — the date you choose sets it. Longer, slower and smaller than a weekday class, and worth arriving early for.',
            min_per_order: 1,
            show_quantity_remaining: true,
            addon_product_ids: [$matHire, $coffee],
            is_highlighted: true,
            highlight_message: 'Small rooms — 12 to 18 places',
            waitlist_enabled: true,
        ))->getId();

        $weekendPriceId = $this->ctx->firstPriceId($weekendSpecial);

        foreach ($weekendPrices as $occurrenceId => $price) {
            $this->ctx->upsertPriceOverride->handle(new UpsertPriceOverrideDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_price_id: $weekendPriceId,
                price: $price,
            ));
        }

        $weekdayProducts = [$dropIn, $community, $firstFree, $matHire, $coffee];
        $weekendProducts = [$weekendSpecial, $matHire, $coffee];

        foreach ($weekendOccurrenceIds as $occurrenceId) {
            $this->ctx->updateVisibility->handle(new UpdateProductVisibilityDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_ids: $weekendProducts,
            ));
        }

        foreach ($this->ctx->occurrenceIdsExcluding($eventId, $weekendOccurrenceIds) as $occurrenceId) {
            $this->ctx->updateVisibility->handle(new UpdateProductVisibilityDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_ids: $weekdayProducts,
            ));
        }

        $classProducts = [$dropIn, $community, $firstFree, $weekendSpecial];

        $this->question($eventId, 'Injuries, conditions, or anything else we should know', 'A teacher reads this before every class. Knees, backs, wrists, shoulders, recent surgery, high or low blood pressure, anything you are managing. We will have props ready and alternatives planned rather than asking you about it in front of the room. Blank is fine too.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::PRODUCT, $classProducts);
        $this->question($eventId, 'Is this your first class at Stillroom?', 'Only so we know to look out for you at the door and show you where things are. Nobody gets announced to the room.', QuestionTypeEnum::RADIO, QuestionBelongsTo::PRODUCT, $classProducts, required: true, options: [
            'Yes, first time here',
            "First time here, but I've practised elsewhere",
            "I've been before",
            "I'm a regular",
        ]);
        $this->question($eventId, 'Are you pregnant or postnatal?', 'Optional, and only asked because some of what we teach changes — twists, deep backbends, lying flat, and breath retention in the breathwork sessions. If you tell us, the teacher will have alternatives ready without drawing attention to it. If you would rather not say, that is completely fine and you can talk to the teacher on the day instead.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::PRODUCT, $classProducts, options: [
            'No',
            'Pregnant — first trimester',
            'Pregnant — second trimester',
            'Pregnant — third trimester',
            'Postnatal, under six months',
            'Postnatal, over six months',
            'Rather not say',
        ]);
        $this->question($eventId, 'Emergency contact', 'Asked for weekend sessions only, because the breathwork and cold water mornings go a bit further than a normal class. Held by the teacher on the day and deleted afterwards.', QuestionTypeEnum::PHONE, QuestionBelongsTo::PRODUCT, [$weekendSpecial]);
        $this->question($eventId, 'Before you book', 'Please tick both. Neither of these is us trying to get out of anything — the first is genuinely the most important thing you can do for your own practice.', QuestionTypeEnum::CHECKBOX, QuestionBelongsTo::ORDER, [], required: true, options: [
            "I'll tell the teacher about anything that changes between now and the class, and come out of a pose if it hurts",
            'I understand a class can be moved free up to 6 hours before, and is used if I cancel inside that',
        ]);
        $this->question($eventId, 'Access needs', 'The studio is on the first floor with seventeen stairs and no lift, which we know rules us out for some people and we are sorry about it. For everything else — a spot near the door, extra props, a quieter corner, coming in ten minutes early to settle before the room fills — just say so here.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'How did you hear about us?', 'We have never run an ad and would like to keep it that way.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::ORDER, [], options: [
            'A friend sent me',
            'The bakery downstairs',
            'Walked past and looked up',
            'Instagram',
            'Google',
            'Came to a weekend special first',
            'Somewhere else',
        ]);
        $this->question($eventId, 'Teacher notes (staff only)', 'Internal. Prop setup, regulars\' preferences, anything carried over from a previous class.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::ORDER, [], isHidden: true);

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'firstmat',
            event_id: $eventId,
            applicable_product_ids: [$firstFree],
            discount_type: PromoCodeDiscountTypeEnum::NONE,
            discount: 0.0,
            expiry_date: $termEnd->setTime(23, 59)->toDateTimeString(),
            max_allowed_usages: 300,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'bringafriend',
            event_id: $eventId,
            applicable_product_ids: [$dropIn],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 50.0,
            expiry_date: $termEnd->setTime(23, 59)->toDateTimeString(),
            max_allowed_usages: 200,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->applySettings($owner->account_id, $eventId, $this->settings());
        $this->ctx->uploadCover($eventId, $owner->account_id, 'yoga.jpg');

        return new SeededDemoEvent(
            event_id: $eventId,
            title: $event->getTitle(),
            slug: $event->getSlug(),
            occurrence_count: $this->ctx->occurrenceCount($eventId),
            promo_codes: ['firstmat', 'bringafriend'],
        );
    }

    private function excludedDates(CarbonImmutable $termStart): array
    {
        $bankHoliday = $termStart->addWeeks(11);
        $closureWeekStart = $termStart->addWeeks(15);

        $dates = [$bankHoliday->toDateString()];

        for ($day = 0; $day < 5; $day++) {
            $dates[] = $closureWeekStart->addDays($day)->toDateString();
        }

        return $dates;
    }

    private function weekendSpecials(): array
    {
        return [
            ['week' => 0, 'day_offset' => 5, 'time' => '10:00', 'minutes' => 90, 'capacity' => 18, 'price' => 32.00, 'label' => 'Candlelit Yin & Sound Bath'],
            ['week' => 1, 'day_offset' => 6, 'time' => '10:00', 'minutes' => 120, 'capacity' => 16, 'price' => 38.00, 'label' => 'Slow Sunday: Restorative & Yoga Nidra'],
            ['week' => 3, 'day_offset' => 5, 'time' => '09:30', 'minutes' => 150, 'capacity' => 14, 'price' => 45.00, 'label' => 'Breathwork & Cold Water'],
            ['week' => 4, 'day_offset' => 6, 'time' => '10:00', 'minutes' => 90, 'capacity' => 18, 'price' => 32.00, 'label' => 'Full Moon Yin & Sound Bath'],
            ['week' => 6, 'day_offset' => 5, 'time' => '10:00', 'minutes' => 180, 'capacity' => 12, 'price' => 55.00, 'label' => 'Handstand Fundamentals'],
            ['week' => 8, 'day_offset' => 6, 'time' => '10:00', 'minutes' => 120, 'capacity' => 16, 'price' => 38.00, 'label' => 'Restorative & Yoga Nidra'],
            ['week' => 11, 'day_offset' => 5, 'time' => '10:00', 'minutes' => 150, 'capacity' => 16, 'price' => 42.00, 'label' => 'Yin for Winter & Tea Ceremony'],
            ['week' => 15, 'day_offset' => 6, 'time' => '10:00', 'minutes' => 150, 'capacity' => 18, 'price' => 42.00, 'label' => 'Solstice Practice & Sound Bath'],
        ];
    }

    private function addon(DemoOwner $owner, int $eventId, int $categoryId, string $title, string $description, float $price): int
    {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: $price)]),
            max_per_order: 2,
            description: $description,
            min_per_order: 1,
            is_addon_only: true,
        ))->getId();
    }

    private function classProduct(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $maxPerOrder,
        array $addonIds,
        bool $showRemaining = false,
    ): int {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: $price)]),
            max_per_order: $maxPerOrder,
            description: $description,
            min_per_order: 1,
            show_quantity_remaining: $showRemaining,
            addon_product_ids: $addonIds,
            waitlist_enabled: true,
        ))->getId();
    }

    private function question(
        int $eventId,
        string $title,
        string $description,
        QuestionTypeEnum $type,
        QuestionBelongsTo $belongsTo,
        array $productIds,
        bool $required = false,
        ?array $options = null,
        bool $isHidden = false,
    ): void {
        $this->ctx->createQuestion->handle(new UpsertQuestionDTO(
            title: $title,
            type: $type,
            required: $required,
            options: $options,
            event_id: $eventId,
            product_ids: $productIds,
            is_hidden: $isHidden,
            belongs_to: $belongsTo,
            description: $description,
        ));
    }

    private function settings(): array
    {
        return [
            'homepage_theme_settings' => [
                'mode' => 'light',
                'accent' => '#B5654A',
                'background' => '#EDE4D8',
                'background_type' => 'COLOR',
                'font_family' => 'Lora',
            ],
            'homepage_background_type' => 'COLOR',
            'homepage_background_color' => '#EDE4D8',
            'homepage_body_background_color' => '#EDE4D8',
            'homepage_primary_color' => '#B5654A',
            'homepage_primary_text_color' => '#FFFFFF',
            'homepage_secondary_color' => '#2E2A24',
            'homepage_secondary_text_color' => '#EDE4D8',
            'seo_title' => 'Stillroom — weekday morning yoga & weekend specials, Dublin 8',
            'seo_description' => 'Two classes every weekday morning at 06:45 and 09:30, plus longer weekend sessions — yin, sound baths, breathwork and nidra. Eighteen mats above a bakery in Dublin 8. Book by the class, no membership.',
            'seo_keywords' => 'yoga dublin 8, morning yoga dublin, vinyasa dublin, yin yoga, sound bath dublin, breathwork, drop in yoga class, blackpitts',
            'allow_search_engine_indexing' => true,
            'price_display_mode' => 'INCLUSIVE',
            'require_attendee_details' => true,
            'attendee_details_collection_method' => 'PER_TICKET',
            'allow_copy_details_to_all_attendees' => false,
            'allow_attendee_self_edit' => true,
            'order_timeout_in_minutes' => 15,
            'continue_button_text' => 'Book a class',
            'support_email' => 'hello@stillroom.ie',
            'show_marketing_opt_in' => true,
            'notify_organizer_of_new_orders' => true,
            'show_available_occurrence_capacity' => true,
            'hide_sold_out_occurrences' => false,
            'waitlist_auto_process' => true,
            'waitlist_offer_timeout_minutes' => 360,
            'pre_checkout_message' => '<p><strong>Two quick things.</strong></p><ul><li>Check the date and time above — a Tuesday 06:45 and a Tuesday 09:30 are very different classes and it is the most common mix-up we see.</li><li>If it is your first time, come ten minutes early. The door is the blue one beside the bakery and it sticks a little.</li></ul>',
            'post_checkout_message' => '<p><strong>You\'re booked. See you upstairs.</strong></p><p>Your date, time and class are in the confirmation email — that is all you need, there is nothing to print and nobody checks a QR code at a yoga class.</p><h3>Before you come</h3><ul><li><strong>Arrive ten minutes early</strong> for your first one so we can say hello and go through your injury notes properly.</li><li><strong>Eat lightly</strong>, or not at all, for an hour or so beforehand. Especially for the 06:45.</li><li><strong>Bring a towel</strong> if you plan to use the shower. There is one, it is small, it works.</li><li><strong>Mats, blocks, straps, bolsters and blankets</strong> are all here. If you added mat hire, yours will be out and ready on a spot.</li></ul><p>Need to move it? Reply to the confirmation email any time up to 6 hours before and we will shift you to another date, no charge and no explanation needed.</p>',
            'email_footer_message' => 'Stillroom · 22 Blackpitts, Dublin 8 · first floor, above the bakery. Move a class free up to 6 hours before.',
            'ticket_design_settings' => [
                'enabled' => true,
                'accent_color' => '#B5654A',
                'layout_type' => 'modern',
                'footer_text' => '22 Blackpitts, Dublin 8 · first floor above the bakery · arrive 10 minutes early for your first class',
            ],
        ];
    }

    private function description(): string
    {
        return '<p><strong>A small room above a bakery in Dublin 8, with good light and eighteen mats.</strong></p>'
            .'<p>Stillroom runs two classes every weekday morning and a slower, longer workshop most weekends. Everything is booked by the class — no membership, no minimum, no contract to cancel. If you come once a month that is genuinely fine.</p>'
            .'<h3>Weekday mornings</h3><ul>'
            .'<li><strong>06:45 — Sunrise Vinyasa</strong> (60 min). Warm, continuous, breath-led. Out the door by 07:50 with time to get to work. Some experience helps but we teach options for everything.</li>'
            .'<li><strong>09:30 — Slow Flow &amp; Mobility</strong> (75 min). Half flow, half floor work on hips, shoulders and spine. The class most people mean when they say they want to start yoga. Complete beginners are welcome without asking first.</li>'
            .'</ul><p>Monday to Friday, all term. Pick any date from the calendar below.</p>'
            .'<h3>Weekend specials</h3>'
            .'<p>One-off longer sessions, capped smaller, usually with two teachers in the room. Candlelit yin with live sound, restorative and nidra afternoons, breathwork, and the occasional handstand workshop for people who want to be upside down. These sell out — they are twelve to eighteen places each.</p>'
            .'<h3>What to expect</h3><ul>'
            .'<li><strong>Turn up ten minutes early</strong> for your first class so we can say hello properly and go through anything in your injury notes.</li>'
            .'<li><strong>Mats, blocks, straps, bolsters and blankets</strong> are all here. Add mat hire at checkout if you would rather not carry one, or bring your own.</li>'
            .'<li><strong>There is a shower</strong>, one, and it is small but it works. Bring a towel.</li>'
            .'<li><strong>No phones in the room.</strong> There is a basket by the door and everyone uses it.</li>'
            .'</ul><h3>If you are new</h3>'
            .'<p>Start with the 09:30 Slow Flow. Use the code <strong>FIRSTMAT</strong> at checkout and your first class is free — one per person, no card details held, nothing to cancel afterwards. If you hate it you never have to see us again.</p>'
            .'<h3>Injuries, pregnancy and everything else</h3>'
            .'<p>There is a notes box at checkout and a teacher reads every one before class. Tell us about injuries, recent surgery, pregnancy or anything you are managing, and we will have props ready and quiet alternatives planned rather than singling you out in the room.</p>'
            .'<h3>Cancelling</h3>'
            .'<p>Cancel up to 6 hours before and we will move you to another date, no charge and no explanation needed. Inside 6 hours we cannot fill the space, so the class is used — though if something genuinely went wrong, email us and we will almost always sort it.</p>'
            .'<hr><p><em>22 Blackpitts, Dublin 8, first floor above the bakery. There are seventeen stairs and no lift, which we know rules us out for some people — we are sorry, and we are working on it.</em></p>';
    }
}
