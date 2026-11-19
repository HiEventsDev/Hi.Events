<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmSegmentPreset: string
{
    use BaseEnum;

    case SUBSCRIBERS = 'subscribers';
    case SMS_SUBSCRIBERS = 'sms_subscribers';
    case FIRST_TIMERS = 'first_timers';
    case REPEAT_BUYERS = 'repeat_buyers';
    case TOP_FANS = 'top_fans';
    case LAPSED = 'lapsed';
    case NO_SHOWS = 'no_shows';
    case MISSED_OUT = 'missed_out';
    case ABANDONED_CHECKOUT = 'abandoned_checkout';

    public function definition(): array
    {
        return match ($this) {
            self::SUBSCRIBERS => $this->all([
                [CrmSegmentField::EMAIL_MARKETING_STATUS, CrmSegmentOperator::IN, ['SUBSCRIBED']],
            ]),
            self::SMS_SUBSCRIBERS => $this->all([
                [CrmSegmentField::SMS_MARKETING_STATUS, CrmSegmentOperator::IN, ['SUBSCRIBED']],
            ]),
            self::FIRST_TIMERS => $this->all([
                [CrmSegmentField::EVENTS_COUNT, CrmSegmentOperator::EQ, 1],
                [CrmSegmentField::FIRST_SEEN_AT, CrmSegmentOperator::WITHIN_DAYS, 90],
            ]),
            self::REPEAT_BUYERS => $this->all([
                [CrmSegmentField::ORDERS_COUNT, CrmSegmentOperator::GTE, 1],
                [CrmSegmentField::EVENTS_COUNT, CrmSegmentOperator::GTE, 2],
            ]),
            self::TOP_FANS => $this->all([
                [CrmSegmentField::EVENTS_COUNT, CrmSegmentOperator::GTE, 4],
            ]),
            self::LAPSED => $this->all([
                [CrmSegmentField::EVENTS_COUNT, CrmSegmentOperator::GTE, 2],
                [CrmSegmentField::LAST_ORDER_AT, CrmSegmentOperator::NOT_WITHIN_DAYS, 180],
            ]),
            self::NO_SHOWS => $this->all([
                [CrmSegmentField::NO_SHOW_COUNT, CrmSegmentOperator::GTE, 2],
            ]),
            self::MISSED_OUT => $this->all([
                [CrmSegmentField::WAITLIST_MISSED_OUT, CrmSegmentOperator::IS_TRUE, null],
            ]),
            self::ABANDONED_CHECKOUT => $this->all([
                [CrmSegmentField::ABANDONED_CHECKOUT, CrmSegmentOperator::IS_TRUE, null],
            ]),
        };
    }

    private function all(array $rules): array
    {
        return [
            'match' => CrmSegmentMatch::ALL->value,
            'rules' => array_map(static fn (array $rule) => [
                'field' => $rule[0]->value,
                'operator' => $rule[1]->value,
                'value' => $rule[2],
            ], $rules),
        ];
    }
}
