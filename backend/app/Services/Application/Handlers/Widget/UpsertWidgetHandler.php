<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Widget;

use Closure;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductCategoryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
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
        $storedSettings = $dto->widget_id === null ? [] : $this->findWidget($dto)->getSettings();

        $settings = array_filter($dto->settings, static fn ($value) => $value !== null);

        if ($dto->event_id !== null) {
            $settings = $this->withEventSelections($dto->event_id, $settings, $storedSettings);
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
    private function findWidget(UpsertWidgetDTO $dto): WidgetDomainObject
    {
        $widget = $this->widgetRepository->findFirstWhere([
            WidgetDomainObjectAbstract::ID => $dto->widget_id,
            WidgetDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            WidgetDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($widget === null) {
            throw new ResourceNotFoundException(__('Widget not found'));
        }

        return $widget;
    }

    /**
     * @throws InvalidWidgetSettingsException
     */
    private function withEventSelections(int $eventId, array $settings, array $storedSettings): array
    {
        $settings = $this->withoutStaleIds(
            settings: $settings,
            storedSettings: $storedSettings,
            key: 'products',
            findExisting: fn (array $ids) => $this->productRepository->findWhereIn(
                ProductDomainObjectAbstract::ID,
                $ids,
                [ProductDomainObjectAbstract::EVENT_ID => $eventId],
            ),
            message: __('One or more selected products do not belong to this event.'),
        );

        $settings = $this->withoutStaleIds(
            settings: $settings,
            storedSettings: $storedSettings,
            key: 'categories',
            findExisting: fn (array $ids) => $this->productCategoryRepository->findWhereIn(
                ProductCategoryDomainObjectAbstract::ID,
                $ids,
                [ProductCategoryDomainObjectAbstract::EVENT_ID => $eventId],
            ),
            message: __('One or more selected categories do not belong to this event.'),
        );

        return $this->withoutStaleOccurrence($eventId, $settings, $storedSettings);
    }

    /**
     * @throws InvalidWidgetSettingsException
     */
    private function withoutStaleIds(array $settings, array $storedSettings, string $key, Closure $findExisting, string $message): array
    {
        if (empty($settings[$key])) {
            return $settings;
        }

        $existingIds = $findExisting($settings[$key])
            ->map(static fn (ProductDomainObject|ProductCategoryDomainObject $domainObject) => $domainObject->getId())
            ->all();
        $missingIds = array_diff($settings[$key], $existingIds);

        if (array_diff($missingIds, $storedSettings[$key] ?? []) !== []) {
            throw new InvalidWidgetSettingsException("settings.$key", $message);
        }

        $settings[$key] = array_values(array_diff($settings[$key], $missingIds));

        if ($settings[$key] === []) {
            unset($settings[$key]);
        }

        return $settings;
    }

    /**
     * @throws InvalidWidgetSettingsException
     */
    private function withoutStaleOccurrence(int $eventId, array $settings, array $storedSettings): array
    {
        if (! isset($settings['occurrence_id']) || $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $settings['occurrence_id'],
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]) !== null) {
            return $settings;
        }

        if ((int) $settings['occurrence_id'] !== (int) ($storedSettings['occurrence_id'] ?? 0)) {
            throw new InvalidWidgetSettingsException('settings.occurrence_id', __('The selected date does not belong to this event.'));
        }

        unset($settings['occurrence_id']);

        return $settings;
    }
}
