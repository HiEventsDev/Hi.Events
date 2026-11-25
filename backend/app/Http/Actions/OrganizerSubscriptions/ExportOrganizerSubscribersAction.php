<?php

namespace HiEvents\Http\Actions\OrganizerSubscriptions;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\OrganizerSubscription\GetOrganizerSubscriptionsRequest;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\ManageOrganizerSubscriptionsHandler;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriberCsv;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrganizerSubscribersAction extends BaseAction
{
    public function __construct(private readonly ManageOrganizerSubscriptionsHandler $handler, private readonly SubscriberCsv $csv) {}

    public function __invoke(GetOrganizerSubscriptionsRequest $request, int $organizerId): StreamedResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);
        $rows = $this->handler->export($organizerId, $request->validated('query'), $request->validated('status'));

        return Response::streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            $this->csv->write($stream, $rows);
            fclose($stream);
        }, 'subscribers.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
