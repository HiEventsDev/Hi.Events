<?php

namespace Tests\Feature\Http\Actions\TaxesAndFees;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TaxOrFeeActionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $this->accountId] = $this->createAccountOwner();
        $this->headers = $this->headersFor($owner, $this->accountId);
    }

    public function test_a_tax_can_be_created_edited_and_deleted(): void
    {
        $taxId = $this->postJson("/accounts/$this->accountId/taxes-and-fees", $this->payload(), $this->headers)
            ->assertOk()
            ->json('data.id');

        $this->putJson("/accounts/$this->accountId/taxes-and-fees/$taxId", $this->payload(['name' => 'Irish VAT', 'rate' => 23]), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $taxId)
            ->assertJsonPath('data.name', 'Irish VAT');

        $this->assertEquals(23, DB::table('taxes_and_fees')->where('id', $taxId)->value('rate'));

        $this->deleteJson("/accounts/$this->accountId/taxes-and-fees/$taxId", [], $this->headers)
            ->assertNoContent();

        $this->assertSoftDeleted('taxes_and_fees', ['id' => $taxId]);
    }

    public function test_another_accounts_tax_cannot_be_edited_or_deleted(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $foreignTaxId = DB::table('taxes_and_fees')->insertGetId([
            'account_id' => $otherAccountId,
            'name' => 'VAT',
            'calculation_type' => 'PERCENTAGE',
            'type' => 'TAX',
            'rate' => 20,
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/accounts/$this->accountId/taxes-and-fees/$foreignTaxId", $this->payload(['rate' => 1]), $this->headers)
            ->assertForbidden();
        $this->deleteJson("/accounts/$this->accountId/taxes-and-fees/$foreignTaxId", [], $this->headers)
            ->assertForbidden();

        $this->assertEquals(20, DB::table('taxes_and_fees')->where('id', $foreignTaxId)->value('rate'));
        $this->assertNotSoftDeleted('taxes_and_fees', ['id' => $foreignTaxId]);
    }

    public function test_a_tax_can_be_created_and_edited_without_a_description(): void
    {
        $payload = $this->payload();
        unset($payload['description']);

        $taxId = $this->postJson("/accounts/$this->accountId/taxes-and-fees", $payload, $this->headers)
            ->assertOk()
            ->json('data.id');

        $this->putJson("/accounts/$this->accountId/taxes-and-fees/$taxId", [...$payload, 'rate' => 23], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.description', null);

        $this->assertEquals(23, DB::table('taxes_and_fees')->where('id', $taxId)->value('rate'));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'VAT',
            'description' => 'Standard rate',
            'calculation_type' => 'PERCENTAGE',
            'type' => 'TAX',
            'rate' => 20,
            'is_active' => true,
            'is_default' => false,
        ], $overrides);
    }
}
