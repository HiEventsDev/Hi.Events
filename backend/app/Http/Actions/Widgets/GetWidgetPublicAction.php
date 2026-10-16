<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Http\Actions\Events\BasePublicEventAction;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use HiEvents\Resources\Widget\WidgetResourcePublic;
use HiEvents\Services\Domain\Widget\WidgetViewIncrementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetWidgetPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly WidgetRepositoryInterface $widgetRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly WidgetViewIncrementService $widgetViewIncrementService,
    ) {}

    public function __invoke(Request $request, string $widgetShortId): Response|JsonResponse
    {
        $widget = $this->widgetRepository->findFirstWhere([
            WidgetDomainObjectAbstract::SHORT_ID => $widgetShortId,
        ]);

        if ($widget === null || ! $this->canUserViewWidgetTarget($widget)) {
            return $this->notFoundResponse();
        }

        if (! $this->isUserAuthenticated()) {
            $this->widgetViewIncrementService->increment($widget->getId(), (string) $this->getClientIp($request));
        }

        return $this->resourceResponse(WidgetResourcePublic::class, $widget);
    }

    private function canUserViewWidgetTarget(WidgetDomainObject $widget): bool
    {
        if ($widget->getEventId() !== null) {
            return $this->canUserViewEventWithId($widget->getEventId());
        }

        return $this->organizerRepository->findFirst($widget->getOrganizerId()) !== null;
    }
}
