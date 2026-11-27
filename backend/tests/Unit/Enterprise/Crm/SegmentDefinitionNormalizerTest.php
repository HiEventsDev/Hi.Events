<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SegmentDefinitionNormalizerTest extends TestCase
{
    private SegmentDefinitionNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new SegmentDefinitionNormalizer;
    }

    public function test_a_valid_definition_is_normalized_to_typed_values(): void
    {
        $normalized = $this->normalizer->normalize([
            'match' => 'any',
            'rules' => [
                ['field' => 'orders_count', 'operator' => 'gte', 'value' => '3', 'ignored' => 'x'],
                ['field' => 'total_spent', 'operator' => 'lte', 'value' => '49.999', 'currency' => 'eur'],
                ['field' => 'last_order_at', 'operator' => 'within_days', 'value' => 30],
                ['field' => 'first_seen_at', 'operator' => 'before', 'value' => '2026-01-31'],
                ['field' => 'last_attended_at', 'operator' => 'is_empty', 'value' => 'anything'],
                ['field' => 'tag', 'operator' => 'in', 'value' => ['7', 7, 9]],
                ['field' => 'email_marketing_status', 'operator' => 'not_in', 'value' => ['SUBSCRIBED', 'SUBSCRIBED', 'BOUNCED']],
                ['field' => 'source', 'operator' => 'in', 'value' => ['IMPORT']],
                ['field' => 'abandoned_checkout', 'operator' => 'is_true', 'value' => 'yes'],
            ],
        ], true);

        $this->assertSame([
            'match' => 'any',
            'rules' => [
                ['field' => 'orders_count', 'operator' => 'gte', 'value' => 3],
                ['field' => 'total_spent', 'operator' => 'lte', 'value' => 50.0, 'currency' => 'EUR'],
                ['field' => 'last_order_at', 'operator' => 'within_days', 'value' => 30],
                ['field' => 'first_seen_at', 'operator' => 'before', 'value' => '2026-01-31'],
                ['field' => 'last_attended_at', 'operator' => 'is_empty', 'value' => null],
                ['field' => 'tag', 'operator' => 'in', 'value' => [7, 9]],
                ['field' => 'email_marketing_status', 'operator' => 'not_in', 'value' => ['SUBSCRIBED', 'BOUNCED']],
                ['field' => 'source', 'operator' => 'in', 'value' => ['IMPORT']],
                ['field' => 'abandoned_checkout', 'operator' => 'is_true', 'value' => null],
            ],
        ], $normalized);
    }

    public function test_match_defaults_to_all_and_rules_default_to_empty(): void
    {
        $this->assertSame(['match' => 'all', 'rules' => []], $this->normalizer->normalize([], false));
    }

    public function test_a_definition_that_is_not_an_array_is_rejected(): void
    {
        $this->assertSame(['definition'], array_keys($this->errorsFor('orders_count >= 1')));
    }

    public function test_an_unknown_match_mode_is_rejected(): void
    {
        $this->assertArrayHasKey('definition.match', $this->errorsFor(['match' => 'some', 'rules' => []]));
    }

    public function test_rules_that_are_not_a_list_are_rejected(): void
    {
        $this->assertArrayHasKey('definition.rules', $this->errorsFor(['rules' => ['a' => ['field' => 'orders_count']]]));
        $this->assertArrayHasKey('definition.rules', $this->errorsFor(['rules' => 'orders_count']));
    }

    public function test_the_maximum_number_of_rules_is_accepted_and_one_more_is_rejected(): void
    {
        $rule = ['field' => 'orders_count', 'operator' => 'gte', 'value' => 1];

        $atLimit = $this->normalizer->normalize(['rules' => array_fill(0, SegmentDefinitionNormalizer::MAX_RULES, $rule)], false);
        $this->assertCount(SegmentDefinitionNormalizer::MAX_RULES, $atLimit['rules']);

        $this->assertArrayHasKey(
            'definition.rules',
            $this->errorsFor(['rules' => array_fill(0, SegmentDefinitionNormalizer::MAX_RULES + 1, $rule)]),
        );
    }

    public static function invalidRules(): array
    {
        return [
            'rule is not an array' => ['orders_count'],
            'unknown field' => [['field' => 'password', 'operator' => 'eq', 'value' => 1]],
            'sql in the field name' => [['field' => 'orders_count; DROP TABLE crm_contacts', 'operator' => 'eq', 'value' => 1]],
            'missing field' => [['operator' => 'eq', 'value' => 1]],
            'unknown operator' => [['field' => 'orders_count', 'operator' => 'like', 'value' => 1]],
            'missing operator' => [['field' => 'orders_count', 'value' => 1]],
            'operator from another kind' => [['field' => 'orders_count', 'operator' => 'in', 'value' => [1]]],
            'date operator on a flag' => [['field' => 'used_promo_code', 'operator' => 'is_empty']],
            'flag operator on a date' => [['field' => 'last_order_at', 'operator' => 'is_true']],
            'negative number' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => -1]],
            'fractional number' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1.5]],
            'fractional number string' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => '1.5']],
            'non numeric number' => [['field' => 'events_count', 'operator' => 'eq', 'value' => 'two']],
            'missing number' => [['field' => 'events_count', 'operator' => 'eq']],
            'number above the ceiling' => [['field' => 'tickets_count', 'operator' => 'lte', 'value' => 1_000_001]],
            'number as an array' => [['field' => 'tickets_count', 'operator' => 'lte', 'value' => [1]]],
            'money without a currency' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 10]],
            'money with a bad currency' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 10, 'currency' => 'EURO']],
            'negative money' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => -1, 'currency' => 'EUR']],
            'non numeric money' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 'lots', 'currency' => 'EUR']],
            'zero days' => [['field' => 'last_order_at', 'operator' => 'within_days', 'value' => 0]],
            'too many days' => [['field' => 'last_order_at', 'operator' => 'not_within_days', 'value' => 3651]],
            'days as a date' => [['field' => 'last_order_at', 'operator' => 'within_days', 'value' => '2026-01-01']],
            'date as days' => [['field' => 'last_order_at', 'operator' => 'before', 'value' => 30]],
            'date in a year the database cannot store' => [['field' => 'last_order_at', 'operator' => 'before', 'value' => '0000-01-01']],
            'impossible date' => [['field' => 'last_order_at', 'operator' => 'after', 'value' => '2026-02-30']],
            'date with a time' => [['field' => 'last_order_at', 'operator' => 'after', 'value' => '2026-02-01 10:00:00']],
            'empty entity list' => [['field' => 'ticket_event', 'operator' => 'in', 'value' => []]],
            'entity value not a list' => [['field' => 'ticket_event', 'operator' => 'in', 'value' => 5]],
            'entity id zero' => [['field' => 'tag', 'operator' => 'in', 'value' => [0]]],
            'entity id not numeric' => [['field' => 'tag', 'operator' => 'in', 'value' => ['1 OR 1=1']]],
            'nested entity id' => [['field' => 'tag', 'operator' => 'in', 'value' => [[1]]]],
            'too many entity ids' => [['field' => 'ticket_product', 'operator' => 'not_in', 'value' => range(1, 51)]],
            'unknown marketing status' => [['field' => 'email_marketing_status', 'operator' => 'in', 'value' => ['SUBSCRIBED', 'MAYBE']]],
            'unknown source' => [['field' => 'source', 'operator' => 'in', 'value' => ['SCRAPED']]],
            'empty option list' => [['field' => 'source', 'operator' => 'in', 'value' => []]],
            'option value not a list' => [['field' => 'source', 'operator' => 'in', 'value' => 'ORDER']],
        ];
    }

    #[DataProvider('invalidRules')]
    public function test_an_invalid_rule_is_rejected_under_its_own_index(mixed $rule): void
    {
        $errors = $this->errorsFor(['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1], $rule]]);

        $this->assertSame(['definition.rules.1'], array_keys($errors));
    }

    public static function malformedShapes(): array
    {
        return [
            'match is an array' => [['match' => ['all'], 'rules' => []]],
            'field is an array' => [['rules' => [['field' => ['orders_count'], 'operator' => 'gte', 'value' => 1]]]],
            'operator is an array' => [['rules' => [['field' => 'orders_count', 'operator' => ['gte'], 'value' => 1]]]],
            'currency is an array' => [['rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1, 'currency' => ['EUR']]]]],
            'option value holds an array' => [['rules' => [['field' => 'source', 'operator' => 'in', 'value' => [['ORDER']]]]]],
        ];
    }

    #[DataProvider('malformedShapes')]
    public function test_malformed_input_shapes_are_rejected_as_validation_errors(array $definition): void
    {
        $this->expectException(InvalidSegmentDefinitionException::class);

        $this->normalizer->normalize($definition, true);
    }

    public function test_spend_rules_are_rejected_for_callers_who_cannot_view_financials(): void
    {
        $definition = ['rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 100, 'currency' => 'USD']]];

        $this->assertSame(['definition.rules.0'], array_keys($this->errorsFor($definition, canViewFinancials: false)));
        $this->assertSame(100.0, $this->normalizer->normalize($definition, true)['rules'][0]['value']);
    }

    public function test_every_invalid_rule_is_reported_at_once(): void
    {
        $errors = $this->errorsFor([
            'match' => 'neither',
            'rules' => [
                ['field' => 'nope', 'operator' => 'eq', 'value' => 1],
                ['field' => 'orders_count', 'operator' => 'eq', 'value' => 1],
                ['field' => 'orders_count', 'operator' => 'eq', 'value' => 'x'],
            ],
        ]);

        $this->assertSame(['definition.match', 'definition.rules.0', 'definition.rules.2'], array_keys($errors));
    }

    /**
     * @return array<string, string>
     */
    private function errorsFor(mixed $definition, bool $canViewFinancials = true): array
    {
        try {
            $this->normalizer->normalize($definition, $canViewFinancials);
        } catch (InvalidSegmentDefinitionException $exception) {
            return $exception->errors();
        }

        $this->fail('The definition was accepted.');
    }
}
