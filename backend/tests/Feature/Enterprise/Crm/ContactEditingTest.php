<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactEditingTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_clearing_a_name_set_by_hand_brings_back_the_name_from_the_contacts_orders(): void
    {
        $contact = "contacts/{$this->contactId}";

        $this->crm('PUT', $contact, ['first_name' => 'Countess', 'last_name' => 'Lovelace'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Countess Lovelace');

        $this->crm('PUT', $contact, ['first_name' => null, 'last_name' => null], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ada')
            ->assertJsonPath('data.last_name', 'Lovelace');
    }

    public function test_clearing_the_name_of_a_contact_without_orders_leaves_it_nameless(): void
    {
        $this->crm('PUT', "contacts/{$this->secondContactId}", ['first_name' => null, 'last_name' => null], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $this->secondContactId)
            ->assertJsonPath('data.first_name', null)
            ->assertJsonPath('data.last_name', null);
    }
}
