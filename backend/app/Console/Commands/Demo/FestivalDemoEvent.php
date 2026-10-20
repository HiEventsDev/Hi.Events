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
use HiEvents\DomainObjects\Status\AffiliateStatus;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Application\Handlers\Affiliate\DTO\UpsertAffiliateDTO;
use HiEvents\Services\Application\Handlers\CapacityAssignment\DTO\UpsertCapacityAssignmentDTO;
use HiEvents\Services\Application\Handlers\CheckInList\DTO\UpsertCheckInListDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;

class FestivalDemoEvent
{
    public const KEY = 'festival';

    private const SITE_CAPACITY = 4000;

    private const CAMPSITE_CAPACITY = 1200;

    public function __construct(
        private readonly DemoSeedContext $ctx,
        private readonly string $timezone,
        private readonly string $currency,
    ) {}

    public function seed(DemoOwner $owner): SeededDemoEvent
    {
        $now = CarbonImmutable::now($this->timezone);
        $gates = $this->gatesOpen($now);
        $close = $gates->addDays(2)->setTime(23, 0);

        $location = $this->ctx->createLocation->handle(new UpsertLocationDTO(
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            name: 'Ardgroom Harbour Farm',
            structured_address: new AddressDTO(
                venue_name: 'Ardgroom Harbour Farm',
                address_line_1: 'Ardgroom',
                address_line_2: 'Beara Peninsula',
                city: 'Bantry',
                state_or_region: 'Co. Cork',
                zip_or_postal_code: 'P75 XR62',
                country: 'IE',
            ),
            latitude: 51.7361,
            longitude: -9.7736,
        ));

        $event = $this->ctx->createEvent->handle(new CreateEventDTO(
            title: 'TIDELINE — Three days on the Beara Peninsula',
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            user_id: $owner->user_id,
            start_date: $gates->toDateTimeString(),
            end_date: $close->toDateTimeString(),
            description: $this->description(),
            attributes: collect([
                new AttributesDTO(name: 'Capacity', value: '4,000', is_public: true),
                new AttributesDTO(name: 'Stages', value: 'The Harbour, The Sea Church, The Boathouse', is_public: true),
                new AttributesDTO(name: 'Camping', value: 'On site — tents, campervans and pre-pitched bell tents', is_public: true),
                new AttributesDTO(name: 'Under 12s', value: 'Free with an accompanying adult', is_public: true),
                new AttributesDTO(name: 'Getting there', value: 'Return shuttle from Cork city, 2h15', is_public: true),
                new AttributesDTO(name: 'Curfew', value: 'Main stages 23:00, Boathouse 03:00', is_public: true),
                new AttributesDTO(name: 'Internal note', value: 'Licence renewal due 8 weeks out, water bowser contract not yet signed', is_public: false),
            ]),
            timezone: $this->timezone,
            currency: $this->currency,
            category: EventCategory::FESTIVAL,
            event_location: new EventLocationData(type: LocationType::IN_PERSON, location_id: $location->getId()),
            status: EventStatus::LIVE->name,
            type: EventType::SINGLE,
        ));

        $eventId = $event->getId();

        $ticketsCategory = $this->ctx->renameDefaultCategory(
            $eventId,
            'Festival tickets',
            'Weekend and day tickets. All of them draw from the same 4,000 site capacity, so when it is gone it is gone.',
        );
        $campingCategory = $this->ctx->addCategory($eventId, 'Camping & accommodation', 'Everything on site opens at noon on the Friday and closes at 2pm on the Monday. 1,200 pitches shared across all four options.');
        $extrasCategory = $this->ctx->addCategory($eventId, 'Getting there & extras', 'Add these to any ticket. The shuttle is the single best decision you will make — the road in is one lane.');

        $shuttle = $this->addon($owner, $eventId, $extrasCategory, 'Return shuttle from Cork city', 'Coach from Parnell Place at 09:00 Friday, back Monday at 11:00. Two hours fifteen each way along the coast road, and it means nobody has to drive home tired.', 25.00, 600, showRemaining: true);
        $parking = $this->addon($owner, $eventId, $extrasCategory, 'Car parking pass', 'One car, one pass, valid all weekend in the field above the harbour. Ten minutes downhill to the gate. There is no parking without a pass and the Gardaí do clear the road.', 20.00, 500);
        $locker = $this->addon($owner, $eventId, $extrasCategory, 'Charging locker', 'A lockable box with a charging cable, by the main gate. Signal is patchy out here anyway, but at least you will get home.', 15.00, 300);
        $tote = $this->addon($owner, $eventId, $extrasCategory, 'TIDELINE tote', 'Screen-printed heavy canvas, made in Cork. Collect from the merch tent by the harbour.', 24.00, 800);

        $ticketAddons = [$shuttle, $parking, $locker, $tote];

        $weekend = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $ticketsCategory,
            title: 'Weekend ticket',
            type: ProductPriceType::TIERED,
            product_type: ProductType::TICKET,
            prices: collect([
                new ProductPriceDTO(price: 189.00, label: 'Tier 1', sale_start_date: $now->subDays(120)->toDateTimeString(), sale_end_date: $now->subDays(45)->toDateTimeString(), initial_quantity_available: 600),
                new ProductPriceDTO(price: 215.00, label: 'Tier 2', sale_start_date: $now->subDays(44)->toDateTimeString(), sale_end_date: $now->addDays(40)->toDateTimeString(), initial_quantity_available: 900),
                new ProductPriceDTO(price: 239.00, label: 'Tier 3', sale_start_date: $now->addDays(41)->toDateTimeString(), sale_end_date: $now->addDays(100)->toDateTimeString(), initial_quantity_available: 900),
                new ProductPriceDTO(price: 265.00, label: 'Final tier', sale_start_date: $now->addDays(101)->toDateTimeString(), sale_end_date: $gates->subDay()->toDateTimeString(), initial_quantity_available: 600),
            ]),
            sale_end_date: $gates->subDay()->toDateTimeString(),
            max_per_order: 6,
            description: 'All three days, both nights, every stage. Camping is separate and sells out first, so if you want a pitch add one now.',
            min_per_order: 1,
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            show_quantity_remaining: true,
            addon_product_ids: $ticketAddons,
            is_highlighted: true,
            highlight_message: 'Tier 2 — the price only goes up',
            waitlist_enabled: true,
        ))->getId();

        $friday = $this->dayTicket($owner, $eventId, $ticketsCategory, 'Friday day ticket', 'Gates noon, music from 14:00, last act 23:00. No camping — day tickets leave the site by 01:00.', 89.00, 400, $gates, $ticketAddons);
        $saturday = $this->dayTicket($owner, $eventId, $ticketsCategory, 'Saturday day ticket', 'The big one. Three stages running from midday, Cormorant closing the harbour at 21:30.', 99.00, 500, $gates, $ticketAddons, highlight: 'Busiest day');
        $sunday = $this->dayTicket($owner, $eventId, $ticketsCategory, 'Sunday day ticket', 'Slower, quieter, and the one the locals come to. Sea Church sessions all afternoon and the Long Table at 17:00.', 89.00, 400, $gates, $ticketAddons);

        $underTwelves = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $ticketsCategory,
            title: 'Under 12s',
            type: ProductPriceType::FREE,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 0.0, initial_quantity_available: 400)]),
            sale_end_date: $gates->subDay()->toDateTimeString(),
            max_per_order: 4,
            description: 'Free, but they still need a ticket so we know how many are on site. Must be with a named adult at all times. Ear defenders at the welfare tent, free, while they last.',
            min_per_order: 1,
        ))->getId();

        $crew = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $ticketsCategory,
            title: 'Crew & artist guest',
            type: ProductPriceType::FREE,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 0.0, initial_quantity_available: 300)]),
            sale_end_date: $gates->addDay()->toDateTimeString(),
            max_per_order: 4,
            description: 'Production, stewards, traders and artist guest list. Your code came from whoever booked you.',
            min_per_order: 1,
            hide_when_sold_out: true,
            is_hidden_without_promo_code: true,
        ))->getId();

        $tent = $this->camping($owner, $eventId, $campingCategory, 'Tent pitch', 'A marked 5m × 5m pitch in the main field for up to two people and one tent. Toilets and drinking water within 100m, hot showers by the harbour wall.', 45.00, 700, $gates, showRemaining: true);
        $quiet = $this->camping($owner, $eventId, $campingCategory, 'Quiet campsite pitch', 'Same pitch, far side of the hill, and a no-sound-systems rule enforced from midnight. For families, light sleepers and anyone who has learned.', 55.00, 250, $gates, showRemaining: true);
        $campervan = $this->camping($owner, $eventId, $campingCategory, 'Campervan or motorhome', 'Hardstanding pitch, 7m max, one vehicle. No electrical hook-up — bring what you need. Vehicle registration required at checkout.', 120.00, 180, $gates, showRemaining: true);
        $glamping = $this->camping($owner, $eventId, $campingCategory, 'Pre-pitched bell tent (sleeps 4)', 'Up before you arrive, taken down after you leave, with real beds, rugs and a lantern. Sleeps four and the price is per tent, not per person.', 480.00, 70, $gates, showRemaining: true, highlight: 'Sells out first');

        $siteTickets = [$weekend, $friday, $saturday, $sunday, $underTwelves, $crew];
        $campingProducts = [$tent, $quiet, $campervan, $glamping];

        $this->ctx->createCapacityAssignment->handle(new UpsertCapacityAssignmentDTO(
            name: 'Festival site capacity',
            event_id: $eventId,
            status: CapacityAssignmentStatus::ACTIVE,
            capacity: self::SITE_CAPACITY,
            product_ids: $siteTickets,
        ));

        $this->ctx->createCapacityAssignment->handle(new UpsertCapacityAssignmentDTO(
            name: 'Campsite pitches',
            event_id: $eventId,
            status: CapacityAssignmentStatus::ACTIVE,
            capacity: self::CAMPSITE_CAPACITY,
            product_ids: $campingProducts,
        ));

        $this->ctx->createCheckInList->handle(new UpsertCheckInListDTO(
            name: 'Main gate — wristband exchange',
            description: 'Every ticket type. Wristband goes on at the gate and does not come off until Monday.',
            eventId: $eventId,
            productIds: $siteTickets,
            expiresAt: $close->addDay()->toDateTimeString(),
            activatesAt: $gates->subHours(2)->toDateTimeString(),
        ));

        $this->ctx->createCheckInList->handle(new UpsertCheckInListDTO(
            name: 'Campsite gate',
            description: 'Pitch allocation and vehicle checks. Campervans are checked against the registration given at checkout.',
            eventId: $eventId,
            productIds: $campingProducts,
            expiresAt: $close->addDay()->toDateTimeString(),
            activatesAt: $gates->subHours(2)->toDateTimeString(),
        ));

        $this->ctx->createAffiliate->handle($eventId, $owner->account_id, new UpsertAffiliateDTO(
            name: 'Cork street team',
            code: 'CORK',
            email: 'streetteam@tideline.ie',
            status: AffiliateStatus::ACTIVE,
        ));

        $this->ctx->createAffiliate->handle($eventId, $owner->account_id, new UpsertAffiliateDTO(
            name: 'Beara Bus partnership',
            code: 'BEARABUS',
            email: 'partners@tideline.ie',
            status: AffiliateStatus::ACTIVE,
        ));

        $allTickets = [$weekend, $friday, $saturday, $sunday, $underTwelves, $crew];

        $this->question($eventId, 'Where should we post your wristband?', 'Weekend wristbands go out by post three weeks before, which means no queue at the gate. We only post within Ireland and the UK — leave this blank and we will hold it at the box office instead.', QuestionTypeEnum::ADDRESS, QuestionBelongsTo::PRODUCT, [$weekend], required: true);
        $this->question($eventId, 'Date of birth', 'Under 18s must be with a responsible adult, and the bar is wristbanded separately at the gate. We check ID on arrival.', QuestionTypeEnum::DATE, QuestionBelongsTo::PRODUCT, $allTickets, required: true);
        $this->question($eventId, 'Which adult will they be with?', 'Full name of the adult responsible for this child on site. They must be on the same order and present at the gate when the wristband goes on.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::PRODUCT, [$underTwelves], required: true);
        $this->question($eventId, 'Vehicle registration', 'Checked against the pass at the gate. If you swap cars before the weekend, email us — it takes ten seconds to change and saves an argument in a field.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::PRODUCT, [$campervan, $parking], required: true);
        $this->question($eventId, 'Emergency contact', 'Someone not coming with you. Held by the welfare team for the weekend and deleted afterwards.', QuestionTypeEnum::PHONE, QuestionBelongsTo::PRODUCT, $allTickets);
        $this->question($eventId, 'Before you buy', 'All three apply to every ticket. The middle one catches people out every year.', QuestionTypeEnum::CHECKBOX, QuestionBelongsTo::ORDER, [], required: true, options: [
            'I have read the terms and the searches-on-entry policy',
            'I understand day tickets do not include camping, and camping is a separate purchase',
            'I know the site is a working farm — uneven ground, no lighting off the main paths, and livestock in the next field',
        ]);
        $this->question($eventId, 'Access requirements', 'There is a viewing platform at the Harbour stage, accessible toilets and showers at both campsites, a charging point for powered chairs at welfare, and a flat gravel route from the accessible campsite to every stage. Personal assistants come free — tell us here and we will send a separate PA ticket.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'Anything we should know about the group?', 'Camping near friends, a birthday, a first festival, someone nervous in crowds. We read these and we do try.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'What are you most here for?', 'Genuinely just for us — it decides what we book next year.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::ORDER, [], options: [
            'The Saturday headliners',
            'The Sea Church sessions',
            'The Boathouse late nights',
            'The Long Table',
            'The swimming',
            'I come every year and do not need a reason',
        ]);
        $this->question($eventId, 'Box office notes (staff only)', 'Internal. Wristband reprints, upgrades, comped tickets and anything the gate needs to know.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::ORDER, [], isHidden: true);

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'tidecrew',
            event_id: $eventId,
            applicable_product_ids: [$crew],
            discount_type: PromoCodeDiscountTypeEnum::NONE,
            discount: 0.0,
            expiry_date: $gates->addDay()->toDateTimeString(),
            max_allowed_usages: 300,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'localsrate',
            event_id: $eventId,
            applicable_product_ids: [$weekend, $friday, $saturday, $sunday],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 25.0,
            expiry_date: $gates->subDay()->toDateTimeString(),
            max_allowed_usages: 400,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->applySettings($owner->account_id, $eventId, $this->settings());
        $this->ctx->uploadCover($eventId, $owner->account_id, 'festival.jpg');

        return new SeededDemoEvent(
            event_id: $eventId,
            title: $event->getTitle(),
            slug: $event->getSlug(),
            occurrence_count: $this->ctx->occurrenceCount($eventId),
            promo_codes: ['tidecrew', 'localsrate'],
        );
    }

    private function gatesOpen(CarbonImmutable $now): CarbonImmutable
    {
        $year = $now->month >= 7 ? $now->year + 1 : $now->year;

        $gates = CarbonImmutable::create($year, 7, 1, 0, 0, 0, $now->timezone)
            ->lastOfMonth(CarbonImmutable::FRIDAY)
            ->setTime(12, 0);

        if ($gates->lt($now->addDays(150))) {
            $gates = $gates->addYear()->lastOfMonth(CarbonImmutable::FRIDAY)->setTime(12, 0);
        }

        return $gates;
    }

    private function addon(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $quantity,
        bool $showRemaining = false,
    ): int {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: $price, initial_quantity_available: $quantity)]),
            max_per_order: 4,
            description: $description,
            min_per_order: 1,
            show_quantity_remaining: $showRemaining,
            is_addon_only: true,
        ))->getId();
    }

    private function dayTicket(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $quantity,
        CarbonImmutable $gates,
        array $addonIds,
        ?string $highlight = null,
    ): int {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: $price, initial_quantity_available: $quantity)]),
            sale_end_date: $gates->subDay()->toDateTimeString(),
            max_per_order: 6,
            description: $description,
            min_per_order: 1,
            show_quantity_remaining: true,
            addon_product_ids: $addonIds,
            is_highlighted: $highlight !== null,
            highlight_message: $highlight,
            waitlist_enabled: true,
        ))->getId();
    }

    private function camping(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $quantity,
        CarbonImmutable $gates,
        bool $showRemaining = false,
        ?string $highlight = null,
    ): int {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: $price, initial_quantity_available: $quantity)]),
            sale_end_date: $gates->subDays(3)->toDateTimeString(),
            max_per_order: 2,
            description: $description,
            min_per_order: 1,
            show_quantity_remaining: $showRemaining,
            is_highlighted: $highlight !== null,
            highlight_message: $highlight,
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
                'mode' => 'dark',
                'accent' => '#FF6B4A',
                'background' => '#071E26',
                'background_type' => 'MIRROR_COVER_IMAGE',
                'font_family' => 'Oswald',
            ],
            'homepage_background_type' => 'MIRROR_COVER_IMAGE',
            'homepage_background_color' => '#071E26',
            'homepage_body_background_color' => '#071E26',
            'homepage_primary_color' => '#FF6B4A',
            'homepage_primary_text_color' => '#071E26',
            'homepage_secondary_color' => '#0E323B',
            'homepage_secondary_text_color' => '#F3E3C8',
            'seo_title' => 'TIDELINE — three days on the Beara Peninsula, West Cork',
            'seo_description' => 'A 4,000-capacity coastal festival on a working farm above Ardgroom harbour. Three stages, camping and campervans on site, under 12s free, return shuttle from Cork city.',
            'seo_keywords' => 'irish festival, west cork festival, beara peninsula, camping festival ireland, boutique festival, cork music festival',
            'allow_search_engine_indexing' => true,
            'price_display_mode' => 'INCLUSIVE',
            'pass_platform_fee_to_buyer' => true,
            'require_attendee_details' => true,
            'attendee_details_collection_method' => 'PER_TICKET',
            'allow_copy_details_to_all_attendees' => true,
            'allow_attendee_self_edit' => true,
            'order_timeout_in_minutes' => 20,
            'continue_button_text' => 'Get tickets',
            'support_email' => 'box.office@tideline.ie',
            'show_marketing_opt_in' => true,
            'notify_organizer_of_new_orders' => true,
            'waitlist_auto_process' => true,
            'waitlist_offer_timeout_minutes' => 1440,
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p><strong>Bank transfer is available on orders of six or more tickets.</strong></p><p>Choose it at checkout and we will hold your tickets for 5 working days while the transfer clears. Transfer to <strong>Tideline Festival Ltd</strong>, IBAN <strong>IE29 AIBK 9311 5212 3456 78</strong>, and put your order reference in the payment reference field — without it we cannot match your payment and the hold will lapse.</p><p>Anything unusual, email <a href="mailto:box.office@tideline.ie">box.office@tideline.ie</a> before you transfer rather than after.</p>',
            'allow_orders_awaiting_offline_payment_to_check_in' => false,
            'pre_checkout_message' => '<p><strong>Two things people get wrong every year.</strong></p><ul><li><strong>Day tickets do not include camping.</strong> If you are staying over you need a pitch as well, and pitches sell out long before tickets do.</li><li><strong>Weekend wristbands are posted out</strong> three weeks before. Get the address right — a wrong address means collecting at the box office in a queue, in the rain, probably.</li></ul><p>Buying for six or more? Bank transfer is available on the next screen.</p>',
            'post_checkout_message' => '<p><strong>You\'re coming. See you above the harbour.</strong></p><p>Weekend wristbands post out three weeks before to the address on your order — change it any time before then from the link in your email. Day tickets and camping are collected at the gate with the QR in your confirmation.</p><h3>Worth knowing now</h3><ul><li><strong>Gates open at noon</strong> on the Friday and the campsites close at 2pm on the Monday.</li><li><strong>The road in is single lane.</strong> If you did not book the shuttle or a parking pass, plan for that — there is nowhere to leave a car.</li><li><strong>It is a working farm.</strong> Bring boots. The forecast is irrelevant, bring boots.</li><li><strong>Swimming</strong> is at the slip below the campsite, lifeguarded 08:00–11:00 only. Not at night, not after the Boathouse.</li></ul><p>The stage times and site map go out a fortnight before. Anything at all: <a href="mailto:box.office@tideline.ie">box.office@tideline.ie</a>.</p>',
            'email_footer_message' => 'TIDELINE · Ardgroom Harbour Farm, Beara Peninsula, Co. Cork. A 4,000-capacity festival on a working farm. Bring boots.',
            'ticket_design_settings' => [
                'enabled' => true,
                'accent_color' => '#FF6B4A',
                'layout_type' => 'modern',
                'footer_text' => 'Wristband exchange at the main gate · Day tickets do not include camping · Under 12s must be with a named adult',
            ],
        ];
    }

    private function description(): string
    {
        return '<p><strong>Four thousand people, three stages and a working farm above Ardgroom harbour, at the far end of the Beara Peninsula.</strong></p>'
            .'<p>TIDELINE is a coastal festival that is deliberately hard to get to. Three days of music that runs from sean-nós in a stone church to a sound system in a boathouse at 3am, on a headland where the Atlantic is on three sides of you and the phone signal gave up years ago.</p>'
            .'<p>It is 4,000 people. It has been 4,000 people every year and it will stay 4,000 people.</p>'
            .'<h3>The stages</h3><ul>'
            .'<li><strong>The Harbour</strong> — the main stage, built into the slope above the slip, facing west. Everything finishes by 23:00 because the licence says so and because the neighbours have been very good to us.</li>'
            .'<li><strong>The Sea Church</strong> — a deconsecrated church up the hill, 180 people, no amplification. Sean-nós, string quartets and whoever asks nicely. Queue early, it fills.</li>'
            .'<li><strong>The Boathouse</strong> — where it goes until 03:00. Low ceiling, concrete floor, and the only part of the site with a proper rig.</li>'
            .'</ul><h3>Getting here, and it matters</h3>'
            .'<p>The site is two and a quarter hours from Cork city on a road that is single lane for the last eleven kilometres. There is no parking without a pass, no taxis after 22:00, and the nearest town is a forty minute walk.</p>'
            .'<p><strong>Take the shuttle.</strong> It leaves Parnell Place at 09:00 on the Friday, comes back Monday at 11:00, and costs less than the diesel. Everyone who drove last year said they would take it this year.</p>'
            .'<h3>Staying</h3>'
            .'<p>1,200 pitches on site, split between the main field, a quiet campsite over the hill with a midnight sound curfew, hardstanding for campervans, and seventy pre-pitched bell tents for people who have decided they are too old for this. Camping is booked separately from your ticket and it always sells out first.</p>'
            .'<p>Day tickets do not include camping. Day ticket holders leave the site by 01:00.</p>'
            .'<h3>Bringing children</h3>'
            .'<p>Under 12s come free but still need a ticket so we know how many are on site, and they need to be with a named adult the whole time. There is a supervised craft tent by the Long Table from 11:00 to 16:00, free ear defenders at welfare while they last, and the quiet campsite exists largely for families.</p>'
            .'<h3>Access</h3>'
            .'<p>A viewing platform at the Harbour stage, accessible toilets and showers at both campsites, a flat gravel route from the accessible camping area to every stage, and a powered-chair charging point at welfare. Personal assistants come free — put it in the access box at checkout and we will send a separate ticket. Parts of the site are a steep field, and we would rather tell you that now than have you find out on the Friday.</p>'
            .'<h3>The unglamorous part</h3>'
            .'<p>It is a farm. The ground is uneven, there is no lighting off the main paths, there is livestock in the next field, and there is a working slipway with deep water at the bottom of the campsite. Swimming is lifeguarded between 08:00 and 11:00 and at no other time — please do not test this.</p>'
            .'<hr><p><em>Ardgroom Harbour Farm, Beara Peninsula, Co. Cork. Bring boots. Bring a warm layer for the evenings, even in July. Leave the site the way you found it.</em></p>';
    }
}
