<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\EventOccurrences;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class UpdateProductVisibilityActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_a_product_listed_twice_is_made_visible_once(): void
    {
        $occurrenceId = $this->insertOccurrence();
        $visibleProductId = $this->insertProduct();
        $this->insertProduct();

        $this->putJson(
            "/events/{$this->eventId}/occurrences/$occurrenceId/product-visibility",
            ['product_ids' => [$visibleProductId, $visibleProductId]],
            ['Authorization' => 'Bearer '.$this->authToken],
        )->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame(
            [$visibleProductId],
            DB::table('product_occurrence_visibility')->where('event_occurrence_id', $occurrenceId)->pluck('product_id')->all(),
        );
    }
}
