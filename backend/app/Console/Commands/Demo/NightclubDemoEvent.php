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
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;

class NightclubDemoEvent
{
    public const KEY = 'nightclub';

    public function __construct(
        private readonly DemoSeedContext $ctx,
        private readonly string $timezone,
        private readonly string $currency,
    ) {}

    public function seed(DemoOwner $owner): SeededDemoEvent
    {
        $doors = CarbonImmutable::now($this->timezone)
            ->addDays(42)
            ->next(CarbonImmutable::SATURDAY)
            ->setTime(23, 0);

        $location = $this->ctx->createLocation->handle(new UpsertLocationDTO(
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            name: 'The Coal Yard',
            structured_address: new AddressDTO(
                venue_name: 'The Coal Yard',
                address_line_1: '14 Newmarket Square',
                address_line_2: 'Unit 3B',
                city: 'Dublin',
                state_or_region: 'Co. Dublin',
                zip_or_postal_code: 'D08 XY42',
                country: 'IE',
            ),
            latitude: 53.3369,
            longitude: -6.2793,
        ));

        $event = $this->ctx->createEvent->handle(new CreateEventDTO(
            title: 'SUBTERRA 004 — Nite Kernel, Ánima, Basil Wren',
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            user_id: $owner->user_id,
            start_date: $doors->toDateTimeString(),
            end_date: $doors->addHours(6)->toDateTimeString(),
            description: $this->description(),
            attributes: collect([
                new AttributesDTO(name: 'Line-up', value: 'Nite Kernel, Ánima, Basil Wren', is_public: true),
                new AttributesDTO(name: 'Sound', value: 'Funktion-One Res 5', is_public: true),
                new AttributesDTO(name: 'Capacity', value: '320', is_public: true),
                new AttributesDTO(name: 'Age policy', value: '18+ with photo ID', is_public: true),
                new AttributesDTO(name: 'Last entry', value: '00:00', is_public: true),
                new AttributesDTO(name: 'Internal note', value: 'Door split 60/40 with venue, settle on the night', is_public: false),
            ]),
            timezone: $this->timezone,
            currency: $this->currency,
            category: EventCategory::NIGHTLIFE,
            event_location: new EventLocationData(type: LocationType::IN_PERSON, location_id: $location->getId()),
            status: EventStatus::LIVE->name,
            type: EventType::SINGLE,
        ));

        $eventId = $event->getId();

        $ticketsCategory = $this->ctx->renameDefaultCategory(
            $eventId,
            'Tickets',
            'Entry for the night. First release is gone — second release is live now.',
            'Tickets are not on sale yet. Join the mailing list and we will tell you first.',
        );
        $extrasCategory = $this->ctx->addCategory($eventId, 'Extras', 'Add these to your order now so you are not queuing for them at 1am.');
        $merchCategory = $this->ctx->addCategory($eventId, 'Merch', 'Screen-printed in Dublin in runs of 100. Collect from the merch table by the cloakroom on the night.');

        $cloakroom = $this->addon($owner, $eventId, $extrasCategory, 'Cloakroom', 'Skip the 3am queue. One hook, one coat, one bag — pre-paid and pre-tagged, collect any time before 05:15. Cameras and anything larger than a rucksack must go in here.', 4.00, 320, 4);
        $earplugs = $this->addon($owner, $eventId, $extrasCategory, 'Reusable earplugs', 'Filtered 19dB plugs in a little metal tin. The Res 5 does not negotiate and neither does tinnitus — genuinely, take a pair.', 3.50, 200, 4);
        $tokens = $this->addon($owner, $eventId, $extrasCategory, 'Bar tokens — pack of 5', 'Five drink tokens for the price of four. Valid on anything behind the bar including the non-alcoholic list, and the water taps are free all night regardless.', 22.00, 400, 4);
        $tee = $this->addon($owner, $eventId, $merchCategory, 'SUBTERRA 004 tee — run of 100', 'Heavyweight 240gsm organic cotton, boxy fit, single-colour acid green discharge print on black. Screen-printed by hand in Dublin 8. Collect from the merch table on the night — we do not post these.', 28.00, 100, 2, showRemaining: true, highlight: '100 only');
        $cassette = $this->addon($owner, $eventId, $merchCategory, 'Basil Wren — Opening Set 003 (cassette)', 'Ninety minutes of the SUBTERRA 003 warm-up, dubbed to chrome tape in an edition of 60, with a hand-numbered J-card. There is no digital version and there will not be one.', 12.00, 60, 2, showRemaining: true);

        $allAddons = [$cloakroom, $earplugs, $tokens, $tee, $cassette];

        $generalAdmission = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $ticketsCategory,
            title: 'General Admission',
            type: ProductPriceType::TIERED,
            product_type: ProductType::TICKET,
            prices: collect([
                new ProductPriceDTO(
                    price: 12.50,
                    label: 'First release',
                    sale_start_date: $doors->subDays(120)->toDateTimeString(),
                    sale_end_date: $doors->subDays(73)->toDateTimeString(),
                    initial_quantity_available: 60,
                ),
                new ProductPriceDTO(
                    price: 16.50,
                    label: 'Second release',
                    sale_start_date: $doors->subDays(72)->toDateTimeString(),
                    sale_end_date: $doors->subDays(21)->toDateTimeString(),
                    initial_quantity_available: 120,
                ),
                new ProductPriceDTO(
                    price: 20.00,
                    label: 'Third release',
                    sale_start_date: $doors->subDays(20)->toDateTimeString(),
                    sale_end_date: $doors->subDays(6)->toDateTimeString(),
                    initial_quantity_available: 100,
                ),
                new ProductPriceDTO(
                    price: 24.00,
                    label: 'Final release',
                    sale_start_date: $doors->subDays(5)->toDateTimeString(),
                    sale_end_date: $doors->subHour()->toDateTimeString(),
                    initial_quantity_available: 40,
                ),
            ]),
            sale_start_date: $doors->subDays(150)->toDateTimeString(),
            sale_end_date: $doors->subHour()->toDateTimeString(),
            max_per_order: 4,
            description: 'Entry from 23:00, last entry 00:00 sharp. Priced in releases — when one sells out the next one opens, and the price only goes up. Bring photo ID.',
            min_per_order: 1,
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            hide_when_sold_out: false,
            show_quantity_remaining: true,
            addon_product_ids: $allAddons,
            is_highlighted: true,
            highlight_message: 'Second release — going fast',
            waitlist_enabled: true,
        ))->getId();

        $concession = $this->ticket($owner, $eventId, $ticketsCategory, 'Concession / unwaged', 'Same ticket, lower price, no questions asked and nothing to prove at the door. If you are unwaged, a student, or money is tight this month, take one of these — that is exactly what they are for.', 9.00, 40, 2, $doors, $allAddons, showRemaining: true);
        $solidarity = $this->ticket($owner, $eventId, $ticketsCategory, 'Solidarity ticket', 'Your entry plus one concession ticket for someone who could not otherwise come. You get in on exactly the same terms as everyone else — the extra tenner just quietly refills the concession pot.', 32.00, 60, 2, $doors, $allAddons);

        $guestlist = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $ticketsCategory,
            title: 'Friends of the Coal Yard',
            type: ProductPriceType::FREE,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 0.0, initial_quantity_available: 30)]),
            sale_end_date: $doors->subHours(3)->toDateTimeString(),
            max_per_order: 2,
            description: 'Residents, artists, hosts and the people who carry the boxes. You know if this is you, and you know the code.',
            min_per_order: 1,
            hide_when_sold_out: true,
            is_hidden_without_promo_code: true,
            addon_product_ids: [$cloakroom, $earplugs, $tokens],
        ))->getId();

        $ticketIds = [$generalAdmission, $concession, $solidarity, $guestlist];

        $this->question($eventId, 'Date of birth', 'Strictly over 18s. We check ID against this at the door and the name on the order has to match it, so please get it right — mismatches get turned away and we cannot refund them.', QuestionTypeEnum::DATE, QuestionBelongsTo::PRODUCT, $ticketIds, required: true);
        $this->question($eventId, 'Emergency contact number', 'Only ever used if something happens to you on the night. Held by the welfare lead, never marketed to, deleted 30 days after the event.', QuestionTypeEnum::PHONE, QuestionBelongsTo::PRODUCT, $ticketIds);
        $this->question($eventId, 'T-shirt size', 'Boxy unisex fit — it runs about one size large, so size down if you want it fitted.', QuestionTypeEnum::RADIO, QuestionBelongsTo::PRODUCT, [$tee], required: true, options: ['S', 'M', 'L', 'XL', '2XL', '3XL']);
        $this->question($eventId, 'The floor policy', 'Tick to confirm you have read these. Door staff and floor hosts enforce all of them and there are no refunds if you are removed.', QuestionTypeEnum::CHECKBOX, QuestionBelongsTo::ORDER, [], required: true, options: [
            'No filming, no flash, no screens on the dancefloor',
            'Zero tolerance for harassment, racism, homophobia or transphobia',
            'Over 18s only — photo ID at the door, no exceptions',
            'Last entry is 00:00 and my ticket is non-transferable',
        ]);
        $this->question($eventId, 'Access requirements', 'Step-free entry, an accessible ground-floor toilet and a quiet room under 70dB are all available on the night. Personal assistants come in free — tell us here and we will have it arranged before you arrive rather than at the door.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'How did you hear about SUBTERRA?', 'We spend nothing on ads, so this genuinely decides where the next one gets posted.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::ORDER, [], options: [
            'A friend dragged me',
            'Was at SUBTERRA 001–003',
            'Instagram',
            'Resident Advisor',
            'Poster in a record shop',
            'Following one of the artists',
            'Somewhere else',
        ]);
        $this->question($eventId, 'Door notes (staff only)', 'Internal field for guestlist annotations. Not shown to ticket buyers.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::ORDER, [], isHidden: true);

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'boxcarrier',
            event_id: $eventId,
            applicable_product_ids: [$guestlist],
            discount_type: PromoCodeDiscountTypeEnum::NONE,
            discount: 0.0,
            expiry_date: $doors->subHours(3)->toDateTimeString(),
            max_allowed_usages: 30,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'earlydoors',
            event_id: $eventId,
            applicable_product_ids: [$generalAdmission],
            discount_type: PromoCodeDiscountTypeEnum::FIXED,
            discount: 3.0,
            expiry_date: $doors->subDays(7)->toDateTimeString(),
            max_allowed_usages: 50,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->applySettings($owner->account_id, $eventId, $this->settings());
        $this->ctx->uploadCover($eventId, $owner->account_id, 'nightclub.jpg');

        return new SeededDemoEvent(
            event_id: $eventId,
            title: $event->getTitle(),
            slug: $event->getSlug(),
            occurrence_count: $this->ctx->occurrenceCount($eventId),
            promo_codes: ['boxcarrier', 'earlydoors'],
        );
    }

    private function addon(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $quantity,
        int $maxPerOrder,
        bool $showRemaining = false,
        ?string $highlight = null,
    ): int {
        return $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $categoryId,
            title: $title,
            type: ProductPriceType::PAID,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: $price, initial_quantity_available: $quantity)]),
            max_per_order: $maxPerOrder,
            description: $description,
            min_per_order: 1,
            hide_when_sold_out: false,
            show_quantity_remaining: $showRemaining,
            is_addon_only: true,
            is_highlighted: $highlight !== null,
            highlight_message: $highlight,
        ))->getId();
    }

    private function ticket(
        DemoOwner $owner,
        int $eventId,
        int $categoryId,
        string $title,
        string $description,
        float $price,
        int $quantity,
        int $maxPerOrder,
        CarbonImmutable $doors,
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
            prices: collect([new ProductPriceDTO(price: $price, initial_quantity_available: $quantity)]),
            sale_start_date: $doors->subDays(150)->toDateTimeString(),
            sale_end_date: $doors->subHour()->toDateTimeString(),
            max_per_order: $maxPerOrder,
            description: $description,
            min_per_order: 1,
            hide_when_sold_out: false,
            show_quantity_remaining: $showRemaining,
            addon_product_ids: $addonIds,
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
                'accent' => '#D0FF14',
                'background' => '#0A0A0B',
                'background_type' => 'COLOR',
                'font_family' => 'Space Grotesk',
            ],
            'homepage_background_type' => 'COLOR',
            'homepage_background_color' => '#0A0A0B',
            'homepage_body_background_color' => '#0A0A0B',
            'homepage_primary_color' => '#D0FF14',
            'homepage_primary_text_color' => '#0A0A0B',
            'homepage_secondary_color' => '#1A1A1D',
            'homepage_secondary_text_color' => '#F2F2EE',
            'seo_title' => 'SUBTERRA 004 — Nite Kernel, Ánima, Basil Wren | The Coal Yard, Dublin 8',
            'seo_description' => 'Six hours, one room, no phones on the floor. Nite Kernel all night long with Ánima and Basil Wren at the Coal Yard, Dublin 8. 320 capacity.',
            'seo_keywords' => 'underground house, dublin nightlife, warehouse party, dublin 8, funktion one, techno, club night, subterra',
            'allow_search_engine_indexing' => true,
            'pre_checkout_message' => '<p><strong>Before you pay, three things.</strong></p><ul><li>Last entry is <strong>00:00</strong>. This is not a soft cut-off — the door shuts and stays shut.</li><li>Bring <strong>photo ID</strong>. The name on this order has to match it, and tickets are non-transferable.</li><li>Phones stay in your pocket on the floor. If you are not up for that, this is the moment to close the tab — no hard feelings.</li></ul>',
            'post_checkout_message' => '<p><strong>You\'re in. See you in the dark.</strong></p><p>Your ticket QR is attached and also lives in the confirmation email — screenshot it now, because there is no signal in the coal store.</p><h3>The short version</h3><ul><li>Doors 23:00, <strong>last entry 00:00</strong>, out at 05:00.</li><li><strong>The Coal Yard</strong>, 14 Newmarket Square, Dublin 8 — side gate on Newmarket Street for step-free entry.</li><li>Photo ID matching the name on this order. No ID, no entry, no refund.</li><li>Anything you added — cloakroom, tokens, merch — is on the same QR. Merch table is by the cloakroom.</li></ul>',
            'email_footer_message' => 'SUBTERRA is a not-for-profit party at the Coal Yard, Dublin 8. Everything above the door fee goes back into the sound, the artists and the hosts.',
            'continue_button_text' => 'Get tickets',
            'support_email' => 'door@subterra.ie',
            'require_attendee_details' => true,
            'allow_copy_details_to_all_attendees' => true,
            'order_timeout_in_minutes' => 15,
            'price_display_mode' => 'INCLUSIVE',
            'show_marketing_opt_in' => true,
            'notify_organizer_of_new_orders' => true,
            'allow_attendee_self_edit' => false,
            'waitlist_auto_process' => true,
            'waitlist_offer_timeout_minutes' => 720,
            'ticket_design_settings' => [
                'enabled' => true,
                'accent_color' => '#D0FF14',
                'layout_type' => 'modern',
                'footer_text' => '18+ · Photo ID required · Last entry 00:00 · Non-transferable · No phones on the floor',
            ],
        ];
    }

    private function description(): string
    {
        return '<p><strong>Six hours. One room. No phones on the floor.</strong></p>'
            .'<p>SUBTERRA returns to the Coal Yard for the fourth time — a stripped-back Victorian coal store off Newmarket Square with a concrete floor, a low ceiling and a Funktion-One Res 5 pointed straight at it. Deep, dubbed-out, slightly broken house music from 11pm until the sun is a problem. Capacity is 320 and we are not adding more.</p>'
            .'<h3>Line-up</h3><ul>'
            .'<li><strong>Nite Kernel</strong> — 01:30–05:00 · Rotterdam. Four years of hardware-only 12&quot;s on Vaulted Tone, and the only person we&rsquo;d trust with the last three and a half hours.</li>'
            .'<li><strong>Ánima</strong> — 00:15–01:30 · Lisbon. Batida rhythms folded into raw Chicago jack. Her <em>Sal e Ferro</em> EP has not left the booth since March.</li>'
            .'<li><strong>Basil Wren</strong> — 23:00–00:15 · Dublin. Resident. Opens properly — dub techno, ambient chords, an hour of patience before anything kicks.</li>'
            .'</ul><h3>Running order</h3><ul>'
            .'<li><strong>23:00</strong> — Doors. Come early, the opening set is the point.</li>'
            .'<li><strong>00:00</strong> — Last entry. We mean it — the door shuts and stays shut.</li>'
            .'<li><strong>05:00</strong> — Lights up, water on the bar, taxis outside.</li>'
            .'</ul><h3>The rules</h3>'
            .'<p>These are not decoration. Door staff and floor hosts enforce all of them.</p><ul>'
            .'<li><strong>Phones down.</strong> No filming, no flash, no screens above waist height on the dancefloor. Cameras go in the cloakroom or you do.</li>'
            .'<li><strong>Look after each other.</strong> Any harassment, racism, homophobia or transphobia gets you removed with no refund and no argument. Find a host in a hi-vis armband if something is off — they are there for exactly this.</li>'
            .'<li><strong>Over 18s only.</strong> Photo ID at the door, no exceptions, no &ldquo;I left it in the car&rdquo;.</li>'
            .'<li><strong>Tickets are non-transferable</strong> and the name on the order must match the ID you present.</li>'
            .'</ul><h3>Access</h3>'
            .'<p>Step-free entry via the Newmarket Street side gate, accessible toilet on the ground floor, and a quiet room off the bar that stays under 70dB all night. Personal assistants come in free — tell us in the accessibility box at checkout and we will sort it before the night.</p>'
            .'<h3>Getting there</h3>'
            .'<p>Eight minutes&rsquo; walk from Dublin 8 / The Coombe, fifteen from Christchurch. Nightlink stops on Cork Street. There is no parking and the neighbours are asleep — please keep it quiet on the way in and on the way out.</p>'
            .'<hr><p><em>SUBTERRA is a not-for-profit party. Everything above the door fee goes back into the sound, the artists and the hosts.</em></p>';
    }
}
