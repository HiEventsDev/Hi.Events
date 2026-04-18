<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class QuestionContactAttributeLinker
{
    private const DEFINITION_TYPE_TEXT = 'text';

    private const DEFINITION_TYPE_SELECT = 'select';

    private const DEFINITION_TYPE_MULTI_SELECT = 'multi_select';

    public function __construct(
        private readonly ContactAttributeDefinitionRepositoryInterface $definitionRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws ValidationException
     */
    public function validate(int $eventId, ?int $definitionId, QuestionTypeEnum $questionType): void
    {
        if ($definitionId === null) {
            return;
        }

        try {
            /** @var ContactAttributeDefinitionDomainObject $definition */
            $definition = $this->definitionRepository->findById($definitionId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'contact_attribute_definition_id' => __('The selected contact attribute does not exist.'),
            ]);
        }

        try {
            $event = $this->eventRepository->findById($eventId);
        } catch (ModelNotFoundException) {
            $event = null;
        }

        if ($event === null || $definition->getAccountId() !== $event->getAccountId()) {
            throw ValidationException::withMessages([
                'contact_attribute_definition_id' => __('The selected contact attribute does not belong to this account.'),
            ]);
        }

        if (! self::isCompatible($questionType, $definition->getType())) {
            throw ValidationException::withMessages([
                'contact_attribute_definition_id' => __('The selected contact attribute type is not compatible with this question type.'),
            ]);
        }
    }

    public static function isCompatible(QuestionTypeEnum $questionType, string $definitionType): bool
    {
        return match ($definitionType) {
            self::DEFINITION_TYPE_TEXT => ! in_array($questionType, [
                QuestionTypeEnum::CHECKBOX,
                QuestionTypeEnum::MULTI_SELECT_DROPDOWN,
            ], true),
            self::DEFINITION_TYPE_SELECT => in_array($questionType, [
                QuestionTypeEnum::RADIO,
                QuestionTypeEnum::DROPDOWN,
            ], true),
            self::DEFINITION_TYPE_MULTI_SELECT => in_array($questionType, [
                QuestionTypeEnum::CHECKBOX,
                QuestionTypeEnum::MULTI_SELECT_DROPDOWN,
            ], true),
            default => false,
        };
    }
}
