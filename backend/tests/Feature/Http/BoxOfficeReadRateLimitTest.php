<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use Tests\TestCase;

class BoxOfficeReadRateLimitTest extends TestCase
{
    private const PER_BOX_OFFICE = 60;

    private const PER_IP = 600;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.api_rate_limit_per_minute' => 100000]);
    }

    public function test_box_offices_on_a_shared_venue_ip_each_have_their_own_allowance(): void
    {
        $this->assertNoneThrottled($this->read('door-a', self::PER_BOX_OFFICE));

        $this->assertNotSame(429, $this->readOnce('door-b'));
        $this->assertSame(429, $this->readOnce('door-a'));
    }

    public function test_door_devices_signed_in_to_the_same_box_office_each_have_their_own_allowance(): void
    {
        $tokens = [$this->doorSessionToken(1), $this->doorSessionToken(2)];

        foreach ($tokens as $token) {
            $this->assertNoneThrottled($this->read('door-a', self::PER_BOX_OFFICE, $token));
        }

        $this->assertNotSame(429, $this->readOnce('door-a'));
        $this->assertSame(429, $this->readOnce('door-a', $tokens[0]));
    }

    public function test_a_made_up_door_session_token_does_not_buy_a_fresh_allowance(): void
    {
        $this->assertNoneThrottled($this->read('door-a', self::PER_BOX_OFFICE));

        $this->assertSame(429, $this->readOnce('door-a', 'bos_made_up'));
    }

    public function test_one_ip_is_capped_across_every_box_office(): void
    {
        for ($boxOffice = 0; $boxOffice < self::PER_IP / self::PER_BOX_OFFICE; $boxOffice++) {
            $this->assertNoneThrottled($this->read('door-'.$boxOffice, self::PER_BOX_OFFICE));
        }

        $this->assertSame(429, $this->readOnce('door-new'));
    }

    private function doorSessionToken(int $device): string
    {
        return app(BoxOfficeSessionService::class)->create(
            boxOffice: (new BoxOfficeDomainObject)->setId(970000 + $device)->setEventId(1)->setPinHash('hash'),
            operatorName: 'Door '.$device,
            eventOccurrenceId: null,
            stripeTerminalReaderId: null,
        )->token;
    }

    /**
     * @return int[]
     */
    private function read(string $shortId, int $times, ?string $sessionToken = null): array
    {
        $statuses = [];

        for ($request = 0; $request < $times; $request++) {
            $statuses[] = $this->readOnce($shortId, $sessionToken);
        }

        return $statuses;
    }

    private function readOnce(string $shortId, ?string $sessionToken = null): int
    {
        $headers = $sessionToken === null ? [] : ['X-Box-Office-Session' => $sessionToken];

        return $this->getJson('/public/box-offices/'.$shortId, $headers)->status();
    }

    /**
     * @param  int[]  $statuses
     */
    private function assertNoneThrottled(array $statuses): void
    {
        $this->assertNotContains(429, $statuses);
    }
}
