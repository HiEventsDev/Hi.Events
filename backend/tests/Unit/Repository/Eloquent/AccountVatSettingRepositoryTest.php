<?php

namespace HiEvents\Tests\Unit\Repository\Eloquent;

use HiEvents\Models\Account;
use HiEvents\Models\AccountVatSetting;
use HiEvents\Repository\Eloquent\AccountVatSettingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountVatSettingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AccountVatSettingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AccountVatSettingRepository($this->app, $this->app->make('db'));
    }

    public function testFindByAccountIdReturnsVatSetting(): void
    {
        $account = Account::factory()->create();
        $vatSetting = AccountVatSetting::factory()->create([
            'account_id' => $account->id,
            'vat_registered' => true,
            'vat_number' => 'IE1234567A',
            'vat_validated' => true,
        ]);

        $result = $this->repository->findByAccountId($account->id);

        $this->assertNotNull($result);
        $this->assertEquals($vatSetting->id, $result->getId());
        $this->assertTrue($result->getVatRegistered());
        $this->assertEquals('IE1234567A', $result->getVatNumber());
    }

    public function testFindByAccountIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByAccountId(99999);

        $this->assertNull($result);
    }

    public function testCreateVatSetting(): void
    {
        $account = Account::factory()->create();

        $vatSetting = $this->repository->create([
            'account_id' => $account->id,
            'vat_registered' => true,
            'vat_number' => 'DE123456789',
            'vat_validated' => false,
            'vat_country_code' => 'DE',
        ]);

        $this->assertNotNull($vatSetting);
        $this->assertEquals($account->id, $vatSetting->getAccountId());
        $this->assertEquals('DE123456789', $vatSetting->getVatNumber());
        $this->assertFalse($vatSetting->getVatValidated());
    }

    public function testUpdateVatSetting(): void
    {
        $account = Account::factory()->create();
        $vatSetting = AccountVatSetting::factory()->create([
            'account_id' => $account->id,
            'vat_validated' => false,
        ]);

        $updated = $this->repository->updateFromArray($vatSetting->id, [
            'vat_validated' => true,
            'business_name' => 'Updated Company Name',
        ]);

        $this->assertTrue($updated->getVatValidated());
        $this->assertEquals('Updated Company Name', $updated->getBusinessName());
    }
}
