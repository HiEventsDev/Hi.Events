<?php

namespace Tests\Unit\Http\Actions\BoxOffices;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\BoxOffice\Http\Actions\Public\GetBoxOfficeOccupiedSeatsPublicAction;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetOccupiedSeatsHandler;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class GetBoxOfficeOccupiedSeatsPublicActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_the_door_sees_seat_status_and_hold_reasons_but_no_attendee_details(): void
    {
        $handler = Mockery::mock(GetOccupiedSeatsHandler::class);
        $handler->shouldReceive('handle')->with(7, 30)->andReturn(collect([
            new OccupiedSeatDTO(
                seat_uid: 'e1.A.1',
                seat_label: 'A-1',
                is_zone: false,
                band_key: 'b_standard',
                status: SeatClaimStatus::SOLD,
                block_reason: null,
                attendee_public_id: 'A-PUB1',
                attendee_name: 'Ada Lovelace',
            ),
            new OccupiedSeatDTO(
                seat_uid: 'e1.A.2',
                seat_label: 'A-2',
                is_zone: false,
                band_key: 'b_standard',
                status: SeatClaimStatus::BLOCKED,
                block_reason: 'Sound desk',
                attendee_public_id: null,
                attendee_name: null,
            ),
        ]));
        $lookup = Mockery::mock(EventSeatMapLookupService::class);
        $lookup->shouldReceive('findForEvent')->andReturnNull();

        $request = Request::create('/public/box-offices/bo_x/occupied-seats');
        $request->attributes->set(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE, (new BoxOfficeDomainObject)->setId(1)->setEventId(7));
        $request->attributes->set(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE, new BoxOfficeSessionDTO(
            box_office_id: 1,
            event_id: 7,
            operator_name: 'Sam',
            event_occurrence_id: 30,
            stripe_terminal_reader_id: null,
            pin_hash: 'hash',
            expires_at: '2030-01-01T00:00:00+00:00',
        ));

        $seats = (new GetBoxOfficeOccupiedSeatsPublicAction($handler, $lookup))($request, 'bo_x')->getData(true)['data'];

        $this->assertSame(['seat_uid', 'seat_label', 'is_zone', 'band_key', 'status', 'block_reason'], array_keys($seats[0]));
        $this->assertSame('SOLD', $seats[0]['status']);
        $this->assertSame('Sound desk', $seats[1]['block_reason']);
    }
}
