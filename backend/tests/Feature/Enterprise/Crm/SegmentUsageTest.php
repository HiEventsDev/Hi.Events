<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentUsageTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_the_segment_list_shows_the_campaigns_and_automations_that_use_each_segment(): void
    {
        $draftId = $this->insertCampaign(['name' => 'Draft news', 'segment_id' => $this->segmentId]);
        $rejectedId = $this->insertCampaign(['name' => 'Rejected news', 'segment_id' => $this->segmentId, 'status' => 'REJECTED']);
        $scheduledId = $this->insertCampaign(['name' => 'Scheduled news', 'segment_id' => $this->segmentId, 'status' => 'SCHEDULED', 'scheduled_at' => now()->addDay()]);
        $this->insertCampaign(['name' => 'Sent news', 'segment_id' => $this->segmentId, 'status' => 'SENT']);
        $this->insertCampaign(['name' => 'Elsewhere', 'segment_id' => $this->segmentId], $this->otherOrganizerId);
        $automationId = $this->insertAutomation('WIN_BACK', true);

        $segment = collect($this->crm('GET', 'segments', [], $this->ownerHeaders())->assertOk()->json('data'))
            ->firstWhere('id', $this->segmentId);

        $this->assertEqualsCanonicalizing([
            ['type' => 'CAMPAIGN', 'id' => $draftId, 'name' => 'Draft news', 'trigger' => null, 'status' => 'DRAFT', 'blocks_deletion' => false],
            ['type' => 'CAMPAIGN', 'id' => $rejectedId, 'name' => 'Rejected news', 'trigger' => null, 'status' => 'REJECTED', 'blocks_deletion' => false],
            ['type' => 'CAMPAIGN', 'id' => $scheduledId, 'name' => 'Scheduled news', 'trigger' => null, 'status' => 'SCHEDULED', 'blocks_deletion' => true],
            ['type' => 'AUTOMATION', 'id' => $automationId, 'name' => null, 'trigger' => 'WIN_BACK', 'status' => 'ENABLED', 'blocks_deletion' => true],
        ], $segment['usages']);
    }

    public function test_saving_a_segment_returns_what_uses_it(): void
    {
        $draftId = $this->insertCampaign(['name' => 'Draft news', 'segment_id' => $this->segmentId]);

        $this->crm('PUT', "segments/{$this->segmentId}", [
            'name' => 'Renamed',
            'definition' => ['match' => 'all', 'rules' => [['field' => 'events_count', 'operator' => 'gte', 'value' => 2]]],
        ], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.usages.0.id', $draftId)
            ->assertJsonPath('data.usages.0.blocks_deletion', false);
    }

    public function test_a_segment_used_by_a_scheduled_campaign_cannot_be_deleted(): void
    {
        $this->insertCampaign(['name' => 'Scheduled news', 'segment_id' => $this->segmentId, 'status' => 'SCHEDULED', 'scheduled_at' => now()->addDay()]);

        $this->crm('DELETE', "segments/{$this->segmentId}", [], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Scheduled news'));

        $this->assertDatabaseHas('crm_segments', ['id' => $this->segmentId]);
    }

    public function test_a_segment_used_by_an_enabled_automation_cannot_be_deleted(): void
    {
        $this->insertAutomation('WIN_BACK', true);

        $this->crm('DELETE', "segments/{$this->segmentId}", [], $this->ownerHeaders())->assertStatus(409);

        $this->assertDatabaseHas('crm_segments', ['id' => $this->segmentId]);
    }

    public function test_a_segment_used_only_by_drafts_and_disabled_automations_can_be_deleted(): void
    {
        $this->insertCampaign(['name' => 'Draft news', 'segment_id' => $this->segmentId]);
        $this->insertAutomation('WIN_BACK', false);
        $this->insertCampaign(['name' => 'Elsewhere', 'segment_id' => $this->segmentId, 'status' => 'SCHEDULED'], $this->otherOrganizerId);

        $this->crm('DELETE', "segments/{$this->segmentId}", [], $this->ownerHeaders())->assertNoContent();

        $this->assertDatabaseMissing('crm_segments', ['id' => $this->segmentId]);
    }

    private function insertAutomation(string $trigger, bool $enabled): int
    {
        return DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => $trigger,
            'is_enabled' => $enabled,
            'subject' => 'We miss you',
            'body' => '<p>Come back</p>',
            'segment_id' => $this->segmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
