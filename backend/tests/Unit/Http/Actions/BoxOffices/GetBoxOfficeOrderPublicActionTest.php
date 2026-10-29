<?php

namespace Tests\Unit\Http\Actions\BoxOffices;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Actions\Public\GetBoxOfficeOrderPublicAction;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\Request;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class GetBoxOfficeOrderPublicActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_a_stripe_outage_during_the_card_poll_is_logged_and_answered_as_a_conflict(): void
    {
        $handler = Mockery::mock(GetBoxOfficeOrderPublicHandler::class);
        $handler->shouldReceive('handle')->andThrow(new ResourceConflictException('Unable to reach Stripe: timeout'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->withArgs(fn (string $message, array $context) => $context['order_short_id'] === 'o_door');

        $request = Request::create('/public/box-offices/bo_x/orders/o_door');
        $request->attributes->set(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE, (new BoxOfficeDomainObject)->setId(1)->setEventId(7));
        $request->attributes->set(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE, new BoxOfficeSessionDTO(
            box_office_id: 1,
            event_id: 7,
            operator_name: 'Sam',
            event_occurrence_id: 30,
            stripe_terminal_reader_id: 9,
            pin_hash: 'hash',
            expires_at: '2030-01-01T00:00:00+00:00',
        ));

        $response = (new GetBoxOfficeOrderPublicAction($handler, $logger))($request, 'bo_x', 'o_door');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Unable to reach Stripe: timeout', $response->getData(true)['message']);
    }
}
