<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment;

use DateTimeImmutable;
use HiEvents\DomainObjects\Enums\CrmContactSource;
use HiEvents\DomainObjects\Enums\CrmSegmentField;
use HiEvents\DomainObjects\Enums\CrmSegmentFieldKind;
use HiEvents\DomainObjects\Enums\CrmSegmentMatch;
use HiEvents\DomainObjects\Enums\CrmSegmentOperator;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;

class SegmentDefinitionNormalizer
{
    public const MAX_RULES = 20;

    private const MAX_ENTITY_VALUES = 50;

    private const MAX_NUMBER = 1_000_000;

    private const MAX_DAYS = 3650;

    private const MIN_YEAR = 1900;

    /**
     * @throws InvalidSegmentDefinitionException
     */
    public function normalize(mixed $definition, bool $canViewFinancials): array
    {
        if (! is_array($definition)) {
            throw new InvalidSegmentDefinitionException(['definition' => __('The segment rules are not valid.')]);
        }

        $match = CrmSegmentMatch::tryFrom($this->stringOf($definition['match'] ?? CrmSegmentMatch::ALL->value));
        $rules = $definition['rules'] ?? [];
        $errors = [];

        if ($match === null) {
            $errors['definition.match'] = __('Choose whether contacts must match all rules or any rule.');
        }

        if (! is_array($rules) || ! array_is_list($rules)) {
            throw new InvalidSegmentDefinitionException(['definition.rules' => __('The segment rules are not valid.')]);
        }

        if (count($rules) > self::MAX_RULES) {
            $errors['definition.rules'] = __('A segment can have at most :max rules.', ['max' => self::MAX_RULES]);
        }

        $normalizedRules = [];
        foreach ($rules as $index => $rule) {
            $key = 'definition.rules.'.$index;
            $normalized = is_array($rule) ? $this->normalizeRule($rule, $canViewFinancials) : __('This rule is not valid.');

            if (is_string($normalized)) {
                $errors[$key] = $normalized;

                continue;
            }

            $normalizedRules[] = $normalized;
        }

        if ($errors !== []) {
            throw new InvalidSegmentDefinitionException($errors);
        }

        return [
            'match' => $match->value,
            'rules' => $normalizedRules,
        ];
    }

    public function isStrippedCopyOf(mixed $submitted, mixed $stored): bool
    {
        try {
            return $this->normalize($submitted, false) == $this->normalize(self::withoutFinancialRules(is_array($stored) ? $stored : []), false);
        } catch (InvalidSegmentDefinitionException) {
            return false;
        }
    }

    public static function hasFinancialRules(mixed $definition): bool
    {
        return array_filter(self::rulesOf($definition), self::isFinancialRule(...)) !== [];
    }

    public static function withoutFinancialRules(array $definition): array
    {
        return [
            ...$definition,
            'rules' => array_values(array_filter(self::rulesOf($definition), static fn (mixed $rule) => ! self::isFinancialRule($rule))),
        ];
    }

    private static function rulesOf(mixed $definition): array
    {
        $rules = is_array($definition) ? $definition['rules'] ?? [] : [];

        return is_array($rules) ? $rules : [];
    }

    private static function isFinancialRule(mixed $rule): bool
    {
        $field = is_array($rule) && is_string($rule['field'] ?? null) ? CrmSegmentField::tryFrom($rule['field']) : null;

        return $field?->isFinancial() ?? false;
    }

    private function normalizeRule(array $rule, bool $canViewFinancials): array|string
    {
        $field = CrmSegmentField::tryFrom($this->stringOf($rule['field'] ?? ''));
        $operator = CrmSegmentOperator::tryFrom($this->stringOf($rule['operator'] ?? ''));

        if ($field === null || $operator === null || ! in_array($operator, $field->kind()->operators(), true)) {
            return __('This rule is not valid.');
        }

        if ($field->isFinancial() && ! $canViewFinancials) {
            return __('You do not have permission to filter by spend.');
        }

        $value = $rule['value'] ?? null;
        $normalized = ['field' => $field->value, 'operator' => $operator->value, 'value' => null];

        switch ($field->kind()) {
            case CrmSegmentFieldKind::NUMBER:
                if (! $this->isWholeNumber($value, 0, self::MAX_NUMBER)) {
                    return __('Enter a whole number.');
                }
                $normalized['value'] = (int) $value;
                break;

            case CrmSegmentFieldKind::MONEY:
                $currency = strtoupper($this->stringOf($rule['currency'] ?? ''));
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 1_000_000_000) {
                    return __('Enter an amount.');
                }
                if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                    return __('Choose a currency.');
                }
                $normalized['value'] = round((float) $value, 2);
                $normalized['currency'] = $currency;
                break;

            case CrmSegmentFieldKind::DATE:
                if ($operator === CrmSegmentOperator::IS_EMPTY) {
                    break;
                }
                if (in_array($operator, [CrmSegmentOperator::WITHIN_DAYS, CrmSegmentOperator::NOT_WITHIN_DAYS], true)) {
                    if (! $this->isWholeNumber($value, 1, self::MAX_DAYS)) {
                        return __('Enter a number of days.');
                    }
                    $normalized['value'] = (int) $value;
                    break;
                }
                $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                if ($date === false || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < self::MIN_YEAR) {
                    return __('Enter a date.');
                }
                $normalized['value'] = $value;
                break;

            case CrmSegmentFieldKind::ENTITY:
                if (! is_array($value) || $value === [] || count($value) > self::MAX_ENTITY_VALUES) {
                    return __('Choose at least one option.');
                }
                foreach ($value as $id) {
                    if (! $this->isWholeNumber($id, 1, PHP_INT_MAX)) {
                        return __('Choose at least one option.');
                    }
                }
                $normalized['value'] = array_values(array_unique(array_map('intval', $value)));
                break;

            case CrmSegmentFieldKind::OPTION:
                $allowed = match ($field) {
                    CrmSegmentField::EMAIL_MARKETING_STATUS => CrmEmailMarketingStatus::valuesArray(),
                    CrmSegmentField::SMS_MARKETING_STATUS => CrmSmsMarketingStatus::valuesArray(),
                    default => CrmContactSource::valuesArray(),
                };
                if (! is_array($value) || $value === [] || array_filter($value, static fn ($option) => ! is_string($option) || ! in_array($option, $allowed, true)) !== []) {
                    return __('Choose at least one option.');
                }
                $normalized['value'] = array_values(array_unique($value));
                break;

            case CrmSegmentFieldKind::FLAG:
                break;
        }

        return $normalized;
    }

    private function stringOf(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function isWholeNumber(mixed $value, int $min, int $max): bool
    {
        if (is_string($value) && preg_match('/^\d+$/', $value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= $min && $value <= $max;
    }
}
