<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalLocationAddress;
use Tests\TestCase;

class TerminalLocationAddressTest extends TestCase
{
    private TerminalLocationAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->address = new TerminalLocationAddress;
    }

    private function organizer(array $structuredAddress): OrganizerDomainObject
    {
        $organizer = (new OrganizerDomainObject)->setId(1)->setName('Venue');
        $organizer->setLocationRecord((new LocationDomainObject)->setStructuredAddress($structuredAddress));

        return $organizer;
    }

    public function test_builds_a_stripe_address_without_empty_fields(): void
    {
        $address = $this->address->fromOrganizer($this->organizer([
            'address_line_1' => '1 Main St',
            'address_line_2' => null,
            'city' => 'Dublin',
            'state_or_region' => 'County Dublin',
            'zip_or_postal_code' => null,
            'country' => 'IE',
        ]));

        $this->assertSame(['line1' => '1 Main St', 'city' => 'Dublin', 'state' => 'County Dublin', 'country' => 'IE'], $address);
    }

    public function test_requires_state_and_postal_code_for_us_addresses(): void
    {
        $this->expectException(TerminalLocationAddressMissingException::class);

        $this->address->fromOrganizer($this->organizer([
            'address_line_1' => '1 Main St',
            'city' => 'Austin',
            'state_or_region' => 'TX',
            'country' => 'US',
        ]));
    }

    public function test_requires_street_city_and_country(): void
    {
        $this->expectException(TerminalLocationAddressMissingException::class);

        $this->address->fromOrganizer($this->organizer(['city' => 'Dublin', 'country' => 'IE']));
    }

    public function test_matches_ignores_case_and_whitespace_but_not_country_changes(): void
    {
        $desired = ['line1' => '1 Main St', 'city' => 'Dublin', 'country' => 'IE'];

        $this->assertTrue($this->address->matches(['line1' => ' 1 main st ', 'line2' => null, 'city' => 'DUBLIN', 'country' => 'ie'], $desired));
        $this->assertFalse($this->address->matches(['line1' => '123 William St', 'city' => 'New York', 'state' => 'NY', 'country' => 'US'], $desired));
        $this->assertFalse($this->address->matches(null, $desired));
    }

    public function test_same_country_ignores_street_changes(): void
    {
        $desired = ['line1' => '1 Main St', 'city' => 'Dublin', 'country' => 'IE'];

        $this->assertTrue($this->address->sameCountry(['line1' => '9 Other St', 'city' => 'Cork', 'country' => 'ie'], $desired));
        $this->assertFalse($this->address->sameCountry(['line1' => '1 Main St', 'city' => 'Dublin', 'country' => 'US'], $desired));
        $this->assertFalse($this->address->sameCountry(null, $desired));
    }
}
