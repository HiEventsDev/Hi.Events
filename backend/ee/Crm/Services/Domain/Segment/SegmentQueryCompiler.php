<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Enums\CrmSegmentField;
use HiEvents\DomainObjects\Enums\CrmSegmentFieldKind;
use HiEvents\DomainObjects\Enums\CrmSegmentMatch;
use HiEvents\DomainObjects\Enums\CrmSegmentOperator;
use HiEvents\DomainObjects\Enums\CrmTicketRole;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;

class SegmentQueryCompiler
{
    private const ACTIVITY = 'SELECT 1 FROM crm_contact_activities a WHERE a.contact_id = crm_contacts.id';

    private const ORGANIZER_LOCAL_MIDNIGHT_IN_UTC = "((CAST(? AS date)::timestamp AT TIME ZONE (SELECT coalesce(nullif(o.timezone, ''), 'UTC') FROM organizers o WHERE o.id = ?)) AT TIME ZONE 'UTC')";

    /**
     * @param  array{match: string, rules: array<int, array{field: string, operator: string, value: mixed, currency?: string}>}  $definition
     */
    public function compile(array $definition, int $organizerId, CarbonInterface $now): CompiledSegmentDTO
    {
        $fragments = [];
        $bindings = [];

        foreach ($definition['rules'] as $rule) {
            [$sql, $ruleBindings] = $this->compileRule($rule, $organizerId, $now);
            $fragments[] = '('.$sql.')';
            array_push($bindings, ...$ruleBindings);
        }

        if ($fragments === []) {
            return new CompiledSegmentDTO(sql: 'TRUE', bindings: []);
        }

        $glue = $definition['match'] === CrmSegmentMatch::ANY->value ? ' OR ' : ' AND ';

        return new CompiledSegmentDTO(sql: '('.implode($glue, $fragments).')', bindings: $bindings);
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function compileRule(array $rule, int $organizerId, CarbonInterface $now): array
    {
        $field = CrmSegmentField::from($rule['field']);
        $operator = CrmSegmentOperator::from($rule['operator']);
        $value = $rule['value'];

        return match ($field->kind()) {
            CrmSegmentFieldKind::NUMBER => $this->compileNumber($field, $operator, (int) $value, $organizerId, $now),
            CrmSegmentFieldKind::MONEY => [
                'coalesce((SELECT s.total_gross - s.total_refunded FROM crm_contact_spend s'
                .' WHERE s.contact_id = crm_contacts.id AND s.currency = ?), 0) '.$this->comparison($operator).' ?',
                [$rule['currency'], (float) $value],
            ],
            CrmSegmentFieldKind::DATE => $this->compileDate($field, $operator, $value, $organizerId, $now),
            CrmSegmentFieldKind::ENTITY => $this->compileEntity($field, $operator, $value, $organizerId),
            CrmSegmentFieldKind::OPTION => [
                'crm_contacts.'.$field->value.($operator === CrmSegmentOperator::NOT_IN ? ' NOT' : '').' IN ('.$this->placeholders($value).')',
                $value,
            ],
            CrmSegmentFieldKind::FLAG => $this->compileFlag($field, $operator),
        };
    }

    private function compileNumber(CrmSegmentField $field, CrmSegmentOperator $operator, int $value, int $organizerId, CarbonInterface $now): array
    {
        if ($field !== CrmSegmentField::NO_SHOW_COUNT) {
            return ['crm_contacts.'.$field->value.' '.$this->comparison($operator).' ?', [$value]];
        }

        if ($operator === CrmSegmentOperator::GTE && $value === 0) {
            return ['TRUE', []];
        }

        [$negate, $having, $count] = match ($operator) {
            CrmSegmentOperator::GTE => [false, '>=', $value],
            CrmSegmentOperator::LTE => [true, '>=', $value + 1],
            default => $value === 0 ? [true, '>=', 1] : [false, '=', $value],
        };

        return [
            'crm_contacts.id '.($negate ? 'NOT IN' : 'IN').' ('.self::noShowContactsSql($having).')',
            [$organizerId, CrmActivityType::TICKET_HELD->value, $now->toDateTimeString(), CrmActivityType::CHECKED_IN->value, CrmActivityType::CHECKED_IN->value, $count],
        ];
    }

    private static function noShowContactsSql(string $having): string
    {
        return 'SELECT no_shows.contact_id FROM ('
            .' SELECT t.contact_id'
            .' FROM crm_contact_activities t'
            .' JOIN event_occurrences eo ON eo.id = t.event_occurrence_id'
            .' WHERE t.organizer_id = ?'
            .' AND t.type = ?'
            ." AND t.role IS DISTINCT FROM '".CrmTicketRole::GUEST->value."'"
            .' AND coalesce(eo.end_date, eo.start_date) < ?'
            .' AND NOT EXISTS ('
            .'   SELECT 1 FROM crm_contact_activities own'
            .'   WHERE own.contact_id = t.contact_id AND own.type = ? AND own.event_occurrence_id = t.event_occurrence_id'
            ."   AND own.role IS DISTINCT FROM '".CrmTicketRole::GUEST->value."'"
            .' )'
            .' AND EXISTS ('
            .'   SELECT 1 FROM crm_contact_activities scanned'
            .'   WHERE scanned.organizer_id = t.organizer_id AND scanned.type = ?'
            .'   AND scanned.event_id = t.event_id AND scanned.event_occurrence_id = t.event_occurrence_id'
            .' )'
            .' GROUP BY t.contact_id, t.event_id, t.event_occurrence_id'
            .') no_shows'
            .' GROUP BY no_shows.contact_id'
            .' HAVING count(*) '.$having.' ?';
    }

    public static function noShowCountSql(): string
    {
        return 'SELECT count(*) FROM ('
            .' SELECT t.event_id, t.event_occurrence_id'
            .' FROM crm_contact_activities t'
            .' JOIN event_occurrences eo ON eo.id = t.event_occurrence_id'
            .' WHERE t.contact_id = crm_contacts.id'
            .' AND t.type = ?'
            ." AND t.role IS DISTINCT FROM '".CrmTicketRole::GUEST->value."'"
            .' AND coalesce(eo.end_date, eo.start_date) < ?'
            .' AND NOT EXISTS ('
            .'   SELECT 1 FROM crm_contact_activities own'
            .'   WHERE own.contact_id = crm_contacts.id AND own.type = ? AND own.event_occurrence_id = t.event_occurrence_id'
            ."   AND own.role IS DISTINCT FROM '".CrmTicketRole::GUEST->value."'"
            .' )'
            .' AND EXISTS ('
            .'   SELECT 1 FROM crm_contact_activities scanned'
            .'   WHERE scanned.organizer_id = crm_contacts.organizer_id AND scanned.type = ?'
            .'   AND scanned.event_id = t.event_id AND scanned.event_occurrence_id = t.event_occurrence_id'
            .' )'
            .' GROUP BY t.event_id, t.event_occurrence_id'
            .') no_shows';
    }

    private function compileDate(CrmSegmentField $field, CrmSegmentOperator $operator, mixed $value, int $organizerId, CarbonInterface $now): array
    {
        $column = 'crm_contacts.'.$field->value;

        return match ($operator) {
            CrmSegmentOperator::WITHIN_DAYS => [$column.' >= ?', [$now->copy()->subDays((int) $value)->toDateTimeString()]],
            CrmSegmentOperator::NOT_WITHIN_DAYS => [$column.' < ?', [$now->copy()->subDays((int) $value)->toDateTimeString()]],
            CrmSegmentOperator::BEFORE => [$column.' < '.self::ORGANIZER_LOCAL_MIDNIGHT_IN_UTC, [$value, $organizerId]],
            CrmSegmentOperator::AFTER => [$column.' >= '.self::ORGANIZER_LOCAL_MIDNIGHT_IN_UTC, [Carbon::parse($value)->addDay()->toDateString(), $organizerId]],
            default => [$column.' IS NULL', []],
        };
    }

    private function compileEntity(CrmSegmentField $field, CrmSegmentOperator $operator, array $ids, int $organizerId): array
    {
        $in = $this->placeholders($ids);

        [$sql, $bindings] = match ($field) {
            CrmSegmentField::TICKET_EVENT => [
                self::ACTIVITY." AND a.type = ? AND a.event_id IN ($in)",
                [CrmActivityType::TICKET_HELD->value, ...$ids],
            ],
            CrmSegmentField::CHECKED_IN_EVENT => [
                self::ACTIVITY." AND a.type = ? AND a.event_id IN ($in)",
                [CrmActivityType::CHECKED_IN->value, ...$ids],
            ],
            CrmSegmentField::TICKET_PRODUCT => [
                self::ACTIVITY." AND a.type = ? AND a.product_id IN ($in)",
                [CrmActivityType::TICKET_HELD->value, ...$ids],
            ],
            CrmSegmentField::TICKET_LOCATION => [
                'SELECT 1 FROM crm_contact_activities a'
                .' JOIN events e ON e.id = a.event_id'
                .' LEFT JOIN event_occurrences eo ON eo.id = a.event_occurrence_id'
                .' JOIN event_locations el ON el.id = coalesce(eo.event_location_id, e.event_location_id) AND el.deleted_at IS NULL'
                ." WHERE a.contact_id = crm_contacts.id AND a.type = ? AND el.location_id IN ($in)",
                [CrmActivityType::TICKET_HELD->value, ...$ids],
            ],
            default => [
                'SELECT 1 FROM crm_contact_tags ct'
                .' JOIN crm_tags tg ON tg.id = ct.tag_id AND tg.organizer_id = ?'
                ." WHERE ct.contact_id = crm_contacts.id AND ct.tag_id IN ($in)",
                [$organizerId, ...$ids],
            ],
        };

        return [($operator === CrmSegmentOperator::NOT_IN ? 'NOT ' : '').'EXISTS ('.$sql.')', $bindings];
    }

    private function compileFlag(CrmSegmentField $field, CrmSegmentOperator $operator): array
    {
        [$sql, $bindings] = match ($field) {
            CrmSegmentField::HAS_PHONE => ['crm_contacts.phone IS NOT NULL', []],
            CrmSegmentField::USED_PROMO_CODE => [
                'EXISTS ('.self::ACTIVITY.' AND a.type = ? AND a.promo_code_id IS NOT NULL)',
                [CrmActivityType::ORDER_COMPLETED->value],
            ],
            CrmSegmentField::WAITLIST_MISSED_OUT => [
                'EXISTS ('.self::ACTIVITY.' AND a.type = ? AND NOT EXISTS ('
                .' SELECT 1 FROM crm_contact_activities held'
                .' WHERE held.contact_id = crm_contacts.id AND held.type = ? AND held.event_id = a.event_id'
                .' AND (a.event_occurrence_id IS NULL OR held.event_occurrence_id = a.event_occurrence_id)'
                .'))',
                [CrmActivityType::WAITLIST_JOINED->value, CrmActivityType::TICKET_HELD->value],
            ],
            default => [
                'EXISTS ('.self::ACTIVITY.' AND a.type = ? AND NOT EXISTS ('
                .' SELECT 1 FROM crm_contact_activities bought'
                .' WHERE bought.contact_id = crm_contacts.id AND bought.type IN (?, ?) AND bought.event_id = a.event_id'
                .' AND bought.occurred_at >= a.occurred_at'
                .'))',
                [CrmActivityType::CHECKOUT_ABANDONED->value, CrmActivityType::ORDER_COMPLETED->value, CrmActivityType::TICKET_HELD->value],
            ],
        };

        return [($operator === CrmSegmentOperator::IS_FALSE ? 'NOT ' : '').$sql, $bindings];
    }

    private function comparison(CrmSegmentOperator $operator): string
    {
        return match ($operator) {
            CrmSegmentOperator::GTE => '>=',
            CrmSegmentOperator::LTE => '<=',
            default => '=',
        };
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
