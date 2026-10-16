<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Widget;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductCategoryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Exceptions\InvalidWidgetSettingsException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use HiEvents\Services\Application\Handlers\Widget\DTO\UpsertWidgetDTO;
use HiEvents\Services\Domain\Widget\WidgetStatsService;

class UpsertWidgetHandler
{
    public function __construct(
        private readonly WidgetRepositoryInterface $widgetRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly WidgetStatsService $widgetStatsService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidWidgetSettingsException
     */
    public function handle(UpsertWidgetDTO $dto): WidgetDomainObject
    {
        if ($dto->widget_id !== null) {
            $this->assertWidgetExists($dto);
        }

        $settings = array_filter($dto->settings, static fn ($value) => $value !== null);

        if ($dto->event_id !== null) {
            $this->assertSettingsBelongToEvent($dto->event_id, $settings);
        }

        $attributes = [
            WidgetDomainObjectAbstract::NAME => trim($dto->name),
            WidgetDomainObjectAbstract::TYPE => $dto->type->value,
            WidgetDomainObjectAbstract::SETTINGS => $settings,
        ];

        $widget = $dto->widget_id === null
            ? $this->widgetRepository->create([
                ...$attributes,
                WidgetDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::WIDGET_PREFIX),
                WidgetDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                WidgetDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                WidgetDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ])
            : $this->widgetRepository->updateFromArray($dto->widget_id, $attributes);

        return $this->widgetStatsService->attachStats(collect([$widget]))->first();
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function assertWidgetExists(UpsertWidgetDTO $dto): void
    {
        $widget = $this->widgetRepository->findFirstWhere([
            WidgetDomainObjectAbstract::ID => $dto->widget_id,
            WidgetDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            WidgetDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($widget === null) {
            throw new ResourceNotFoundException(__('Widget not found'));
        }
    }

    /**
     * @throws InvalidWidgetSettingsException
     */
    private function assertSettingsBelongToEvent(int $eventId, array $settings): void
    {
        $productIds = array_values(array_unique($settings['products'] ?? []));
        if ($productIds !== [] && $this->productRepository->findWhereIn(
            ProductDomainObjectAbstract::ID,
            $productIds,
            [ProductDomainObjectAbstract::EVENT_ID => $eventId],
        )->count() !== count($productIds)) {
            throw new InvalidWidgetSettingsException('settings.products', __('One or more selected products do not belong to this event.'));
        }

        $categoryIds = array_values(array_unique($settings['categories'] ?? []));
        if ($categoryIds !== [] && $this->productCategoryRepository->findWhereIn(
            ProductCategoryDomainObjectAbstract::ID,
            $categoryIds,
            [ProductCategoryDomainObjectAbstract::EVENT_ID => $eventId],
        )->count() !== count($categoryIds)) {
            throw new InvalidWidgetSettingsException('settings.categories', __('One or more selected categories do not belong to this event.'));
        }

        if (isset($settings['occurrence_id']) && $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $settings['occurrence_id'],
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]) === null) {
            throw new InvalidWidgetSettingsException('settings.occurrence_id', __('The selected date does not belong to this event.'));
        }
    }
}
