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
use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\Enums\TaxType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;

class ConferenceDemoEvent
{
    public const KEY = 'conference';

    public function __construct(
        private readonly DemoSeedContext $ctx,
        private readonly string $timezone,
        private readonly string $currency,
    ) {}

    public function seed(DemoOwner $owner): SeededDemoEvent
    {
        $day1 = CarbonImmutable::now($this->timezone)
            ->addDays(90)
            ->next(CarbonImmutable::THURSDAY)
            ->setTime(9, 0);
        $day2 = $day1->addDay()->setTime(18, 0);
        $workshopDay = $day1->subDay();

        $vatId = $this->ctx->taxOrFeeId($owner->account_id, 'VAT', TaxCalculationType::PERCENTAGE, TaxType::TAX, 23.0, 'Irish VAT at the standard 23% rate, applied to conference passes and workshops.');
        $feeId = $this->ctx->taxOrFeeId($owner->account_id, 'Booking fee', TaxCalculationType::FIXED, TaxType::FEE, 2.50, 'Covers payment processing and badge printing.');

        $location = $this->ctx->createLocation->handle(new UpsertLocationDTO(
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            name: 'The Round Room at the Mansion House',
            structured_address: new AddressDTO(
                venue_name: 'The Round Room at the Mansion House',
                address_line_1: 'Dawson Street',
                city: 'Dublin',
                state_or_region: 'Co. Dublin',
                zip_or_postal_code: 'D02 AF30',
                country: 'IE',
            ),
            latitude: 53.3398,
            longitude: -6.2578,
        ));

        $event = $this->ctx->createEvent->handle(new CreateEventDTO(
            title: 'RUNTIME 26 — Two days on the systems behind the systems',
            organizer_id: $owner->organizer_id,
            account_id: $owner->account_id,
            user_id: $owner->user_id,
            start_date: $day1->toDateTimeString(),
            end_date: $day2->toDateTimeString(),
            description: $this->description(),
            attributes: collect([
                new AttributesDTO(name: 'Format', value: 'Single track, 32 talks, 25 minutes each', is_public: true),
                new AttributesDTO(name: 'Capacity', value: '800', is_public: true),
                new AttributesDTO(name: 'Workshops', value: 'The day before, four full-day sessions', is_public: true),
                new AttributesDTO(name: 'Recordings', value: 'Free and public within two weeks', is_public: true),
                new AttributesDTO(name: 'Accessibility', value: 'Step-free, live captioning, hearing loop, quiet room', is_public: true),
                new AttributesDTO(name: 'Internal note', value: 'AV contract signed, catering final numbers due two weeks out', is_public: false),
            ]),
            timezone: $this->timezone,
            currency: $this->currency,
            category: EventCategory::TECH,
            event_location: new EventLocationData(type: LocationType::IN_PERSON, location_id: $location->getId()),
            status: EventStatus::LIVE->name,
            type: EventType::SINGLE,
        ));

        $eventId = $event->getId();

        $passesCategory = $this->ctx->renameDefaultCategory($eventId, 'Conference passes', 'Both days, single track, all meals. Prices exclude VAT — your invoice will itemise it.');
        $workshopCategory = $this->ctx->addCategory($eventId, 'Workshops — the day before', 'Full-day, hands-on, capped small so you actually get help. Bring a laptop. Lunch included.', 'Workshops are announced with the second wave of speakers.');
        $extrasCategory = $this->ctx->addCategory($eventId, 'Extras', 'Optional bits. The community fund is the one that matters most.');

        $tee = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $extrasCategory,
            title: 'RUNTIME 26 tee',
            type: ProductPriceType::PAID,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: 32.00, initial_quantity_available: 400)]),
            max_per_order: 3,
            description: 'Heavyweight organic cotton, screen-printed in Dublin, with the conference dependency graph on the back. Collect from registration on day one — we do not post them.',
            min_per_order: 1,
            tax_and_fee_ids: [$vatId],
            is_addon_only: true,
        ))->getId();

        $dinner = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $extrasCategory,
            title: "Speakers' dinner — evening of day one",
            type: ProductPriceType::PAID,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: 65.00, initial_quantity_available: 80)]),
            max_per_order: 2,
            description: 'Long tables, no seating plan, every speaker in the room and no assigned VIP section. 80 seats, allocated in order of purchase. Dietary requirements come from your attendee form.',
            min_per_order: 1,
            show_quantity_remaining: true,
            tax_and_fee_ids: [$vatId],
            is_addon_only: true,
            is_highlighted: true,
            highlight_message: '80 seats',
        ))->getId();

        $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $extrasCategory,
            title: 'Community ticket fund',
            type: ProductPriceType::DONATION,
            product_type: ProductType::GENERAL,
            prices: collect([new ProductPriceDTO(price: 20.00)]),
            max_per_order: 1,
            description: 'Pay what you like. Every €75 here becomes one community pass for someone whose employer will not pay and who is not going to ask twice. We publish exactly what came in and what went out after the event.',
            min_per_order: 1,
        ));

        $workshops = [];
        foreach ($this->workshops() as $workshop) {
            $workshops[] = $this->ctx->createProduct->handle(new UpsertProductDTO(
                account_id: $owner->account_id,
                event_id: $eventId,
                product_category_id: $workshopCategory,
                title: $workshop['title'],
                type: ProductPriceType::PAID,
                product_type: ProductType::TICKET,
                prices: collect([new ProductPriceDTO(price: $workshop['price'], initial_quantity_available: $workshop['seats'])]),
                sale_start_date: $day1->subDays(199)->toDateTimeString(),
                sale_end_date: $workshopDay->subDay()->setTime(23, 0)->toDateTimeString(),
                max_per_order: 2,
                description: $workshop['description'],
                min_per_order: 1,
                show_quantity_remaining: true,
                tax_and_fee_ids: [$vatId],
                is_highlighted: $workshop['highlight'] !== null,
                highlight_message: $workshop['highlight'],
                waitlist_enabled: true,
            ))->getId();
        }

        $conferencePass = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $passesCategory,
            title: 'Conference pass',
            type: ProductPriceType::TIERED,
            product_type: ProductType::TICKET,
            prices: collect([
                new ProductPriceDTO(price: 195.00, label: 'Blind bird', sale_start_date: $day1->subDays(300)->toDateTimeString(), sale_end_date: $day1->subDays(200)->toDateTimeString(), initial_quantity_available: 100),
                new ProductPriceDTO(price: 275.00, label: 'Early bird', sale_start_date: $day1->subDays(199)->toDateTimeString(), sale_end_date: $day1->subDays(120)->toDateTimeString(), initial_quantity_available: 250),
                new ProductPriceDTO(price: 345.00, label: 'Standard', sale_start_date: $day1->subDays(119)->toDateTimeString(), sale_end_date: $day1->subDays(27)->toDateTimeString(), initial_quantity_available: 300),
                new ProductPriceDTO(price: 425.00, label: 'Final release', sale_start_date: $day1->subDays(26)->toDateTimeString(), sale_end_date: $day1->subDay()->setTime(23, 0)->toDateTimeString(), initial_quantity_available: 150),
            ]),
            sale_start_date: $day1->subDays(320)->toDateTimeString(),
            sale_end_date: $day1->subDay()->setTime(23, 0)->toDateTimeString(),
            max_per_order: 4,
            description: 'Both days, single track, breakfast, lunch and coffee included, recordings after. Priced in releases — when one sells out the next opens and the price only goes up. Prices exclude VAT.',
            min_per_order: 1,
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            show_quantity_remaining: true,
            tax_and_fee_ids: [$vatId, $feeId],
            addon_product_ids: [$tee, $dinner],
            is_highlighted: true,
            highlight_message: 'Standard release — the final release costs more',
            waitlist_enabled: true,
        ))->getId();

        $teamPass = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $passesCategory,
            title: 'Team pass — 5 seats or more',
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 295.00, initial_quantity_available: 150)]),
            sale_start_date: $day1->subDays(320)->toDateTimeString(),
            sale_end_date: $day1->subDays(7)->toDateTimeString(),
            max_per_order: 25,
            description: 'Same pass, a lower price per seat, minimum five in one order. One invoice, one PO, one expense claim for whoever drew the short straw. Attendee names can be filled in later — email us and we will reopen the form up to a week before.',
            min_per_order: 5,
            tax_and_fee_ids: [$vatId, $feeId],
            addon_product_ids: [$tee, $dinner],
        ))->getId();

        $communityPass = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $passesCategory,
            title: 'Community pass',
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 75.00, initial_quantity_available: 100)]),
            sale_start_date: $day1->subDays(160)->toDateTimeString(),
            sale_end_date: $day1->subDays(14)->toDateTimeString(),
            max_per_order: 1,
            description: 'Funded entirely by the community fund and by people buying solidarity tickets. For students, career changers, people between jobs, and anyone whose employer will not pay. There is no means test — we ask which describes you and we take your word for it.',
            min_per_order: 1,
            show_quantity_remaining: true,
            addon_product_ids: [$tee],
            is_highlighted: true,
            highlight_message: 'Funded by the community fund',
            waitlist_enabled: true,
        ))->getId();

        $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $passesCategory,
            title: 'Livestream pass',
            type: ProductPriceType::PAID,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 45.00, initial_quantity_available: 2000)]),
            sale_start_date: $day1->subDays(320)->toDateTimeString(),
            sale_end_date: $day2->toDateTimeString(),
            max_per_order: 10,
            description: 'Both days streamed live with captions, plus the Q&A channel and the recordings a week before they go public. No hallway track, which is honestly most of the value — but it is the same talks.',
            min_per_order: 1,
            tax_and_fee_ids: [$vatId],
        ));

        $speakerPass = $this->ctx->createProduct->handle(new UpsertProductDTO(
            account_id: $owner->account_id,
            event_id: $eventId,
            product_category_id: $passesCategory,
            title: 'Speaker & crew',
            type: ProductPriceType::FREE,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: 0.0, initial_quantity_available: 90)]),
            sale_end_date: $day1->subDay()->setTime(23, 0)->toDateTimeString(),
            max_per_order: 2,
            description: 'Speakers, volunteers, AV and the code of conduct team. You will have been sent a code.',
            min_per_order: 1,
            hide_when_sold_out: true,
            is_hidden_without_promo_code: true,
            addon_product_ids: [$tee, $dinner],
        ))->getId();

        $badgePasses = [$conferencePass, $teamPass, $communityPass, $speakerPass];

        $this->question($eventId, 'Name as you want it on your badge', 'This is what gets printed and what 800 people will read across a room. Nicknames, mononyms and handles are all fine — it does not have to match your ID.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::PRODUCT, $badgePasses, required: true);
        $this->question($eventId, 'Job title', 'Printed under your name. Leave it blank if you would rather people just talked to you.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::PRODUCT, $badgePasses);
        $this->question($eventId, 'Company or organisation', 'Also printed on the badge. Between jobs is a perfectly good answer and several of us have used it.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::PRODUCT, $badgePasses);
        $this->question($eventId, 'Pronouns for your badge', 'Optional, and blank is a valid choice rather than a missing one.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::PRODUCT, $badgePasses, options: ['she / her', 'he / him', 'they / them', 'she / they', 'he / they', 'Ask me', 'Leave it off the badge']);
        $this->question($eventId, 'Dietary requirements', 'Select everything that applies. This goes straight to the caterer as a headcount, and there is a labelled table for allergens rather than one sad tray at the end of the line.', QuestionTypeEnum::MULTI_SELECT_DROPDOWN, QuestionBelongsTo::PRODUCT, array_merge($badgePasses, $workshops), options: ['No requirements', 'Vegetarian', 'Vegan', 'Gluten-free', 'Dairy-free', 'Nut allergy', 'Shellfish allergy', 'Halal', 'Kosher', 'Low FODMAP']);
        $this->question($eventId, 'What do you want to get out of this workshop?', 'A sentence is plenty. Instructors read these the week before and rebalance the day around what people actually turned up for.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::PRODUCT, $workshops);
        $this->question($eventId, 'Which of these describes you?', 'We ask so we can report back to the people funding these passes. We do not verify it and there is nothing to upload.', QuestionTypeEnum::RADIO, QuestionBelongsTo::PRODUCT, [$communityPass], required: true, options: ['Student or apprentice', 'Career changer', 'Between jobs', 'Employer will not cover it', 'Independent, freelance or non-profit', 'Would rather not say']);
        $this->question($eventId, 'T-shirt size', 'Unisex fit, true to size. Collect from registration on day one.', QuestionTypeEnum::DROPDOWN, QuestionBelongsTo::PRODUCT, [$tee], required: true, options: ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL']);
        $this->question($eventId, 'Code of conduct', 'Please read it in full on the website before ticking. The response team is named there with direct contact details, not a general inbox.', QuestionTypeEnum::CHECKBOX, QuestionBelongsTo::ORDER, [], required: true, options: [
            'I have read the code of conduct and agree to it',
            'I understand it is enforced, and that removal for harassment does not come with a refund',
        ]);
        $this->question($eventId, 'Company VAT number', 'For the reverse charge if you are VAT-registered outside Ireland. Leave blank and we will just charge Irish VAT at 23%. It appears on the invoice exactly as you type it, so check it.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'Accessibility requirements', 'The venue is step-free throughout, the main hall has a hearing loop and live captioning on both screens, and there is a quiet room off the hallway. Tell us anything else — reserved seating near the front, an interpreter, a personal assistant ticket at no charge, a fridge for medication — and we will have it sorted well before you arrive.', QuestionTypeEnum::MULTI_LINE_TEXT, QuestionBelongsTo::ORDER, []);
        $this->question($eventId, 'Hiring, or looking?', 'Optional. Gets you a small coloured dot on your badge and a line on the jobs board by the coffee. Nothing is shared with sponsors.', QuestionTypeEnum::RADIO, QuestionBelongsTo::ORDER, [], options: ['Hiring', 'Looking', 'Both, somehow', 'Neither']);
        $this->question($eventId, 'Account notes (staff only)', 'Internal field for PO numbers and group-booking context. Not shown to buyers.', QuestionTypeEnum::SINGLE_LINE_TEXT, QuestionBelongsTo::ORDER, [], isHidden: true);

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'speaker26',
            event_id: $eventId,
            applicable_product_ids: [$speakerPass],
            discount_type: PromoCodeDiscountTypeEnum::NONE,
            discount: 0.0,
            expiry_date: $day1->subDay()->setTime(23, 0)->toDateTimeString(),
            max_allowed_usages: 90,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'usergroup',
            event_id: $eventId,
            applicable_product_ids: [$conferencePass],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 15.0,
            expiry_date: $day1->subDays(27)->toDateTimeString(),
            max_allowed_usages: 120,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->createPromoCode->handle($eventId, new UpsertPromoCodeDTO(
            code: 'alumni25',
            event_id: $eventId,
            applicable_product_ids: [$conferencePass, $teamPass],
            discount_type: PromoCodeDiscountTypeEnum::FIXED,
            discount: 50.0,
            expiry_date: $day1->subDays(5)->toDateTimeString(),
            max_allowed_usages: 200,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $this->ctx->applySettings($owner->account_id, $eventId, $this->settings());
        $this->ctx->uploadCover($eventId, $owner->account_id, 'conference.jpg');

        return new SeededDemoEvent(
            event_id: $eventId,
            title: $event->getTitle(),
            slug: $event->getSlug(),
            occurrence_count: $this->ctx->occurrenceCount($eventId),
            promo_codes: ['speaker26', 'usergroup', 'alumni25'],
        );
    }

    private function workshops(): array
    {
        return [
            [
                'title' => 'Postgres at 10TB',
                'price' => 195.00,
                'seats' => 30,
                'highlight' => '30 seats',
                'description' => 'A full day in a real 10TB database. Partitioning strategies you can apply without downtime, what autovacuum is actually doing while you sleep, reading query plans that lie to you, and the three connection-pooling mistakes behind most 3am pages. Bring a laptop with Docker.',
            ],
            [
                'title' => "Agents that don't hallucinate their way into prod",
                'price' => 195.00,
                'seats' => 30,
                'highlight' => 'Nearly gone',
                'description' => 'Shipping LLM-backed systems you can actually operate: tool-call validation, retry and fallback design, structured output that holds under load, cost and latency budgets, and an eval harness you build on the day and take home. Bring a laptop and an API key.',
            ],
            [
                'title' => "Incident command for engineers who'd rather not",
                'price' => 160.00,
                'seats' => 25,
                'highlight' => null,
                'description' => 'Three escalating simulated incidents. You will take the commander role at least once. Covers declaring early, running a channel that stays readable, handing over cleanly at shift change, and writing the review without blame. No laptop needed.',
            ],
            [
                'title' => 'The far end of the TypeScript type system',
                'price' => 160.00,
                'seats' => 25,
                'highlight' => null,
                'description' => 'Conditional and mapped types, template literal types, variance, and knowing when to stop. Half the day is spent deleting types that were never earning their keep. Bring a laptop and a codebase you are allowed to show.',
            ],
        ];
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
                'accent' => '#1B36F5',
                'background' => '#F2F0EA',
                'background_type' => 'COLOR',
                'font_family' => 'Plus Jakarta Sans',
            ],
            'homepage_background_type' => 'COLOR',
            'homepage_background_color' => '#F2F0EA',
            'homepage_body_background_color' => '#F2F0EA',
            'homepage_primary_color' => '#1B36F5',
            'homepage_primary_text_color' => '#FFFFFF',
            'homepage_secondary_color' => '#14161A',
            'homepage_secondary_text_color' => '#F2F0EA',
            'seo_title' => 'RUNTIME 26 — Single-track engineering conference, Dublin',
            'seo_description' => 'Two days on the systems behind the systems. 32 single-track talks on databases, infrastructure, AI in production and incident response. The Round Room, Dublin.',
            'seo_keywords' => 'engineering conference dublin, software conference ireland, infrastructure conference, postgres, sre, ai infrastructure, developer conference',
            'allow_search_engine_indexing' => true,
            'price_display_mode' => 'EXCLUSIVE',
            'require_attendee_details' => true,
            'attendee_details_collection_method' => 'PER_TICKET',
            'allow_copy_details_to_all_attendees' => true,
            'allow_attendee_self_edit' => true,
            'order_timeout_in_minutes' => 30,
            'continue_button_text' => 'Get your pass',
            'support_email' => 'tickets@runtime.dev',
            'show_marketing_opt_in' => true,
            'notify_organizer_of_new_orders' => true,
            'waitlist_auto_process' => true,
            'waitlist_offer_timeout_minutes' => 2880,
            'enable_invoicing' => true,
            'invoice_label' => 'Invoice',
            'invoice_prefix' => 'RT26',
            'invoice_start_number' => 1001,
            'require_billing_address' => true,
            'invoice_payment_terms_days' => 30,
            'organization_name' => 'Runtime Events Ltd',
            'organization_address' => '6 Fitzwilliam Square, Dublin 2, D02 XE61, Ireland',
            'invoice_tax_details' => 'Runtime Events Ltd · Company no. 719284 · VAT no. IE4392017T',
            'invoice_notes' => 'Payment due within 30 days. Please quote the invoice number on your remittance. For PO numbers or a quote before purchase, email tickets@runtime.dev.',
            'pre_checkout_message' => '<p><strong>Two things before you pay.</strong></p><ul><li>Badge details are collected per attendee. If you are buying for a team you can copy the shared fields down and just change the names.</li><li>Prices exclude VAT — the 23% is itemised on the next screen and on your invoice. Add a VAT number below if you are registered outside Ireland.</li></ul><p>Need a PO raised, a quote first, or an invoice before payment? Stop here and email <a href="mailto:tickets@runtime.dev">tickets@runtime.dev</a> — we turn these around the same day.</p>',
            'post_checkout_message' => '<p><strong>You\'re in. See you at the Round Room.</strong></p><p>Your VAT invoice is attached to the confirmation email — that is the one your finance team wants. Passes are QR codes in the same email; there is nothing to print.</p><h3>Between now and then</h3><ul><li><strong>Badge details</strong> can be edited any time up to a week before from the link in your email. Change the name, fix the job title, add dietary requirements you forgot.</li><li><strong>Workshops</strong>, if you booked one, are the day before. Room assignments go out the week before.</li><li><strong>Day one</strong> — registration opens 08:15, first talk 09:00 sharp.</li></ul><p>The schedule lands a few weeks out, and every ticket holder gets it a day before it is public.</p>',
            'email_footer_message' => 'RUNTIME 26 · The Round Room at the Mansion House, Dawson Street, Dublin 2. Runtime Events Ltd, 6 Fitzwilliam Square, Dublin 2. VAT IE4392017T.',
            'ticket_design_settings' => [
                'enabled' => true,
                'accent_color' => '#1B36F5',
                'layout_type' => 'modern',
                'footer_text' => 'Registration opens 08:15 on day one · Bring this QR · Badge details editable until a week before',
            ],
        ];
    }

    private function description(): string
    {
        return '<p><strong>Two days, one track, 32 talks about the unglamorous parts of software that actually decide whether it works.</strong></p>'
            .'<p>RUNTIME is a single-track conference for people who operate what they build. No keynote sponsors, no product pitches from the main stage, no talk that could have been a landing page. Everything is 25 minutes, everything is recorded, and every speaker has run the thing they are describing in production and can tell you how it went wrong.</p>'
            .'<p>We cap it at 800 so the hallway track still works.</p>'
            .'<h3>What the two days look like</h3><ul>'
            .'<li><strong>Day one</strong> — <em>State &amp; Scale.</em> Databases, queues, storage, and the long tail of what happens at 3am. Doors and coffee 08:15, first talk 09:00, close 18:00.</li>'
            .'<li><strong>Day two</strong> — <em>Models &amp; Machines.</em> Inference infrastructure, evals, agents in production, and the cost of all of it. Same times, closing panel at 17:00.</li>'
            .'<li><strong>The day before</strong> — optional full-day workshops at the Mansion House. Capped at 25–30 people each, and they do sell out.</li>'
            .'</ul><h3>Some of who is speaking</h3><ul>'
            .'<li><strong>Dr. Amara Osei</strong> — <em>Consensus is not your bottleneck, your disks are.</em> Ten years of distributed storage postmortems, condensed.</li>'
            .'<li><strong>Tomás Ferreira</strong> — <em>Postgres at 10TB: partitioning, vacuum, and the pager.</em></li>'
            .'<li><strong>Lin Zhao</strong> — <em>What inference actually costs you</em>, including the parts your invoice does not itemise.</li>'
            .'<li><strong>Niamh Brennan</strong> — <em>Incident command for engineers who would rather not be in charge.</em></li>'
            .'<li><strong>Kwame Adjei</strong> — <em>Developer experience is a latency problem.</em></li>'
            .'<li><strong>Sofia Lindqvist</strong> — <em>Evals that survive contact with users.</em></li>'
            .'</ul><p>The remaining 26 talks come off an open CFP. Blind-reviewed, and we pay every speaker.</p>'
            .'<h3>What is included</h3><ul>'
            .'<li>Both conference days, single track, no upgrade tier and no roped-off seating.</li>'
            .'<li>Breakfast, lunch and proper coffee on both days. The dietary form is real and we actually read it.</li>'
            .'<li>Talk recordings within two weeks, free and public, captioned.</li>'
            .'<li>Live captioning in the room, a quiet room off the main hall, and a hallway track with enough seating to hold a conversation in.</li>'
            .'</ul><h3>Getting a ticket paid for</h3>'
            .'<p>Tick the invoice box at checkout and you will get a proper VAT invoice with 30-day terms — enough for most expense policies. If you need a letter for your manager, a quote before purchase, or a PO raised, email <a href="mailto:tickets@runtime.dev">tickets@runtime.dev</a> and we will send one the same day.</p>'
            .'<p>If your employer will not pay and that is the only thing stopping you, take a community pass. There is no means test and we do not ask.</p>'
            .'<h3>Code of conduct</h3>'
            .'<p>We have one, it is enforced, and the response team is named on the website with direct contact details rather than a general inbox. Harassment gets people removed without a refund.</p>'
            .'<hr><p><em>The Round Room at the Mansion House, Dawson Street, Dublin 2. Step-free throughout, hearing loop in the main hall, five minutes from Grafton Street and the Luas green line.</em></p>';
    }
}
