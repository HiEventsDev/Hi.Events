<?php

namespace Tests\Feature\Http\Actions\Badges;

use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesBadgeFixtures;
use Tests\TestCase;

class BadgesTest extends TestCase
{
    use CreatesBadgeFixtures;
    use DatabaseTransactions;

    public function test_an_unsaved_design_uses_defaults_that_pick_up_company_and_job_title_questions(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId, 'USD');
        $companyId = $this->createQuestion($eventId, 'Company name');
        $jobTitleId = $this->createQuestion($eventId, 'Job title');
        $this->createQuestion($eventId, 'Dietary requirements');

        $design = $this->getJson("/events/$eventId/badge-design", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->json('data');

        $this->assertFalse($design['is_customized']);
        $this->assertSame('IN_4X3_LANDSCAPE', $design['size']);
        $this->assertSame('LETTER', $design['sheet']);
        $this->assertSame(
            [['FIRST_NAME', null], ['LAST_NAME', null], ['QUESTION', $companyId], ['QUESTION', $jobTitleId]],
            array_map(fn (array $line) => [$line['source'], $line['question_id']], $design['lines']),
        );
    }

    public function test_badges_are_switched_on_per_event_from_event_settings(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId, badgesEnabled: false);
        DB::table('event_settings')->where('event_id', $eventId)->update(['payment_providers' => json_encode(['STRIPE'])]);
        $headers = $this->headersFor($owner, $accountId);

        $this->getJson("/events/$eventId/settings", $headers)->assertOk()->assertJsonPath('data.enabled_features', []);

        $this->patchJson("/events/$eventId/settings", ['enabled_features' => ['BADGES', 'BADGES']], $headers)->assertOk();
        $this->patchJson("/events/$eventId/settings", ['waitlist_auto_process' => false], $headers)->assertOk();
        $this->getJson("/events/$eventId/settings", $headers)->assertOk()->assertJsonPath('data.enabled_features', ['BADGES']);

        $this->patchJson("/events/$eventId/settings", ['enabled_features' => ['SPACESHIPS']], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enabled_features.0');

        $this->patchJson("/events/$eventId/settings", ['enabled_features' => []], $headers)->assertOk();
        $this->getJson("/events/$eventId/settings", $headers)->assertOk()->assertJsonPath('data.enabled_features', []);
    }

    public function test_a_design_is_saved_and_returned(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId, 'Speaker');
        $questionId = $this->createQuestion($eventId, 'Company');
        $headers = $this->headersFor($owner, $accountId);

        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'template' => 'BANNER',
            'lines' => [
                ['source' => 'FULL_NAME', 'size' => 'XL', 'text' => 'ignored'],
                ['source' => 'QUESTION', 'size' => 'M', 'question_id' => $questionId],
                ['source' => 'CUSTOM_TEXT', 'size' => 'S', 'text' => 'Ask me about Hi.Events'],
            ],
            'ribbons' => [['product_id' => $productId, 'label' => 'SPEAKER', 'color' => '#b91c1c']],
            'back_mode' => 'SAME',
            'fold_over' => true,
        ]), $headers)->assertOk();

        $design = $this->getJson("/events/$eventId/badge-design", $headers)->assertOk()->json('data');

        $this->assertTrue($design['is_customized']);
        $this->assertSame('BANNER', $design['template']);
        $this->assertSame('SAME', $design['back_mode']);
        $this->assertTrue($design['fold_over']);
        $this->assertNull($design['lines'][0]['text']);
        $this->assertSame($questionId, $design['lines'][1]['question_id']);
        $this->assertSame('Ask me about Hi.Events', $design['lines'][2]['text']);
        $this->assertSame([['product_id' => $productId, 'label' => 'SPEAKER', 'color' => '#b91c1c']], $design['ribbons']);
    }

    public function test_a_design_cannot_reference_another_events_question_or_ticket(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $otherEventId = $this->createBadgeEvent($accountId);
        $headers = $this->headersFor($owner, $accountId);

        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'lines' => [['source' => 'QUESTION', 'size' => 'M', 'question_id' => $this->createQuestion($otherEventId, 'Company')]],
        ]), $headers)->assertStatus(422)->assertJsonValidationErrors(['lines.0.question_id']);

        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'ribbons' => [['product_id' => $this->createTicketProduct($otherEventId), 'label' => null, 'color' => '#000000']],
        ]), $headers)->assertStatus(422)->assertJsonValidationErrors(['ribbons.0.product_id']);

        $this->assertNull(DB::table('event_settings')->where('event_id', $eventId)->value('badge_design_settings'));
    }

    public function test_design_validation_rejects_bad_colours_and_duplicate_ribbons(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId);

        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'accent_color' => 'red;}body{display:none',
            'size' => 'CUSTOM',
            'ribbons' => [
                ['product_id' => $productId, 'label' => null, 'color' => '#000000'],
                ['product_id' => $productId, 'label' => null, 'color' => '#ffffff'],
            ],
        ]), $this->headersFor($owner, $accountId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accent_color', 'custom_width_mm', 'custom_height_mm', 'ribbons.1.product_id']);
    }

    public function test_read_only_members_can_view_but_not_change_badges(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $viewer = $this->addAccountUser($accountId, Role::VIEWER);
        $headers = $this->headersFor($viewer, $accountId);

        $this->getJson("/events/$eventId/badge-design", $headers)->assertOk();
        $this->getJson("/events/$eventId/badges", $headers)->assertOk();
        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload(), $headers)->assertForbidden();
        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [$attendee['id']]], $headers)->assertForbidden();
    }

    public function test_badges_resolve_answers_ribbons_and_skip_ineligible_attendees(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $speakerId = $this->createTicketProduct($eventId, 'Speaker pass');
        $generalId = $this->createTicketProduct($eventId, 'General');
        $companyId = $this->createQuestion($eventId, 'Company');
        $teamId = $this->createQuestion($eventId, 'Team', 'ORDER');
        $ada = $this->createBadgeAttendee($eventId, $speakerId, 'Ada', 'Lovelace');
        $grace = $this->createBadgeAttendee($eventId, $generalId, 'Grace', 'Hopper');
        $this->createBadgeAttendee($eventId, $generalId, 'Cancelled', 'Person', 'CANCELLED');
        $this->createBadgeAttendee($eventId, $generalId, 'Unpaid', 'Person', 'AWAITING_PAYMENT', 'AWAITING_OFFLINE_PAYMENT');
        $this->answerQuestion($companyId, $ada['order_id'], $ada['id'], 'Analytical Engines', $speakerId);
        $this->answerQuestion($teamId, $grace['order_id'], null, ['Navy', 'Compilers']);
        $headers = $this->headersFor($owner, $accountId);

        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'lines' => [
                ['source' => 'FIRST_NAME', 'size' => 'XL'],
                ['source' => 'QUESTION', 'size' => 'M', 'question_id' => $companyId],
                ['source' => 'QUESTION', 'size' => 'S', 'question_id' => $teamId],
            ],
            'ribbons' => [['product_id' => $speakerId, 'label' => 'SPEAKER', 'color' => '#b91c1c']],
        ]), $headers)->assertOk();

        $badges = $this->getJson("/events/$eventId/badges?sort_by=first_name", $headers)->assertOk()->json('data');

        $this->assertSame(['Ada', 'Grace'], array_column($badges, 'first_name'));
        $this->assertSame([['FIRST_NAME', 'Ada'], ['QUESTION', 'Analytical Engines']], array_map(fn ($line) => [$line['source'], $line['text']], $badges[0]['lines']));
        $this->assertSame(['label' => 'SPEAKER', 'color' => '#b91c1c'], $badges[0]['ribbon']);
        $this->assertSame([['FIRST_NAME', 'Grace'], ['QUESTION', 'Navy, Compilers']], array_map(fn ($line) => [$line['source'], $line['text']], $badges[1]['lines']));
        $this->assertSame(['label' => 'General', 'color' => '#1d4ed8'], $badges[1]['ribbon']);
    }

    public function test_attendees_awaiting_offline_payment_are_included_when_they_can_check_in(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Unpaid', 'Person', 'AWAITING_PAYMENT', 'AWAITING_OFFLINE_PAYMENT');
        DB::table('event_settings')->where('event_id', $eventId)->update(['allow_orders_awaiting_offline_payment_to_check_in' => true]);

        $this->getJson("/events/$eventId/badges", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.0.first_name', 'Unpaid');
    }

    public function test_badges_filter_by_ids_order_ticket_and_print_status(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $speakerId = $this->createTicketProduct($eventId, 'Speaker');
        $generalId = $this->createTicketProduct($eventId, 'General');
        $ada = $this->createBadgeAttendee($eventId, $speakerId, 'Ada', 'Lovelace');
        $grace = $this->createBadgeAttendee($eventId, $generalId, 'Grace', 'Hopper');
        $alan = $this->createBadgeAttendee($eventId, $generalId, 'Alan', 'Turing', orderId: $grace['order_id']);
        $foreign = $this->createBadgeAttendee($this->createBadgeEvent($accountId), $speakerId, 'Not', 'Mine');
        DB::table('attendees')->where('id', $ada['id'])->update(['badge_printed_at' => now(), 'badge_print_count' => 1]);
        $headers = $this->headersFor($owner, $accountId);

        $names = fn (string $query) => array_column($this->getJson("/events/$eventId/badges?$query", $headers)->assertOk()->json('data'), 'first_name');

        $this->assertSame(['Grace', 'Alan'], $names('attendee_ids='.$grace['id'].','.$alan['id'].','.$foreign['id'].'&sort_by=created_at'));
        $this->assertSame(['Grace', 'Alan'], $names('order_id='.$grace['order_id'].'&sort_by=created_at'));
        $this->assertSame(['Ada'], $names("product_ids=$speakerId"));
        $this->assertSame(['Grace', 'Alan'], $names('only_unprinted=true&sort_by=created_at'));
        $this->assertSame(['Grace', 'Ada', 'Alan'], $names('sort_by=last_name'));
    }

    public function test_marking_badges_printed_updates_stats_and_name_changes_flag_a_reprint(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId);
        $ada = $this->createBadgeAttendee($eventId, $productId, 'Ada', 'Lovelace');
        $grace = $this->createBadgeAttendee($eventId, $productId, 'Grace', 'Hopper');
        $headers = $this->headersFor($owner, $accountId);

        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [$ada['id'], $grace['id']]], $headers)
            ->assertOk()
            ->assertJsonPath('data.updated', 2);
        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [$ada['id']]], $headers)->assertOk();

        $this->getJson("/events/$eventId/badges/stats", $headers)
            ->assertOk()
            ->assertJsonPath('data', ['total' => 2, 'printed' => 2, 'not_printed' => 0, 'needs_reprint' => 0]);
        $this->assertSame(2, DB::table('attendees')->where('id', $ada['id'])->value('badge_print_count'));

        $this->patchJson("/events/$eventId/attendees/{$ada['id']}", ['first_name' => 'Augusta'], $headers)
            ->assertOk()
            ->assertJsonPath('data.badge_print_status', 'NEEDS_REPRINT');

        $this->getJson("/events/$eventId/badges/stats", $headers)
            ->assertJsonPath('data', ['total' => 2, 'printed' => 1, 'not_printed' => 0, 'needs_reprint' => 1]);
    }

    public function test_marking_another_events_attendee_printed_is_rejected_before_any_write(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $mine = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $otherEventId = $this->createBadgeEvent($accountId);
        $foreign = $this->createBadgeAttendee($otherEventId, $this->createTicketProduct($otherEventId), 'Not', 'Mine');

        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [$mine['id'], $foreign['id']]], $this->headersFor($owner, $accountId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_ids']);

        $this->assertSame(0, DB::table('attendees')->whereIn('id', [$mine['id'], $foreign['id']])->sum('badge_print_count'));
    }

    public function test_marking_printed_rejects_duplicate_ids(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $ada = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');

        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [$ada['id'], $ada['id']]], $this->headersFor($owner, $accountId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_ids']);
        $this->postJson("/events/$eventId/badges/printed", ['attendee_ids' => [[1], [2]]], $this->headersFor($owner, $accountId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_ids.0']);
        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'ribbons' => [['product_id' => [1], 'label' => null, 'color' => '#000000']],
        ]), $this->headersFor($owner, $accountId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ribbons.0.product_id']);
    }

    public function test_editing_an_answer_on_the_badge_flags_a_reprint(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId);
        $companyId = $this->createQuestion($eventId, 'Company');
        $ada = $this->createBadgeAttendee($eventId, $productId, 'Ada', 'Lovelace');
        $this->answerQuestion($companyId, $ada['order_id'], $ada['id'], 'Analytical Engines', $productId);
        DB::table('attendees')->where('id', $ada['id'])->update(['badge_printed_at' => now(), 'badge_print_count' => 1]);
        $answerId = DB::table('question_answers')->where('question_id', $companyId)->value('id');

        $this->putJson("/events/$eventId/questions/$companyId/answers/$answerId", ['answer' => 'Difference Engines'], $this->headersFor($owner, $accountId))
            ->assertSuccessful();

        $this->assertNull(DB::table('attendees')->where('id', $ada['id'])->value('badge_printed_at'));
    }

    public function test_duplicating_an_event_carries_the_badge_design_onto_the_new_ids(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId, 'Speaker');
        DB::table('products')->where('id', $productId)->update([
            'product_category_id' => DB::table('product_categories')->insertGetId([
                'event_id' => $eventId,
                'name' => 'Tickets',
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ]);
        DB::table('product_prices')->insert(['product_id' => $productId, 'price' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $companyId = $this->createQuestion($eventId, 'Company');
        DB::table('product_questions')->insert(['product_id' => $productId, 'question_id' => $companyId]);
        $headers = $this->headersFor($owner, $accountId);
        $this->putJson("/events/$eventId/badge-design", $this->badgeDesignPayload([
            'lines' => [['source' => 'FIRST_NAME', 'size' => 'XL'], ['source' => 'QUESTION', 'size' => 'M', 'question_id' => $companyId]],
            'ribbons' => [['product_id' => $productId, 'label' => 'SPEAKER', 'color' => '#b91c1c']],
        ]), $headers)->assertOk();

        $newEventId = $this->postJson("/events/$eventId/duplicate", [
            'title' => 'Copy',
            'start_date' => now()->addMonth()->format('Y-m-d H:i'),
            'duplicate_products' => true,
            'duplicate_questions' => true,
            'duplicate_settings' => true,
            'duplicate_promo_codes' => false,
            'duplicate_capacity_assignments' => false,
            'duplicate_check_in_lists' => false,
            'duplicate_event_cover_image' => false,
            'duplicate_ticket_logo' => false,
            'duplicate_webhooks' => false,
            'duplicate_affiliates' => false,
        ], $headers)->assertSuccessful()->json('data.id');

        $design = $this->getJson("/events/$newEventId/badge-design", $headers)->assertOk()->json('data');
        $newQuestionId = DB::table('questions')->where('event_id', $newEventId)->value('id');
        $newProductId = DB::table('products')->where('event_id', $newEventId)->where('title', 'Speaker')->value('id');

        $this->assertSame($newQuestionId, $design['lines'][1]['question_id']);
        $this->assertSame($newProductId, $design['ribbons'][0]['product_id']);
    }
}
