<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmSegmentFieldKind: string
{
    case NUMBER = 'number';
    case MONEY = 'money';
    case DATE = 'date';
    case ENTITY = 'entity';
    case OPTION = 'option';
    case FLAG = 'flag';

    /**
     * @return CrmSegmentOperator[]
     */
    public function operators(): array
    {
        return match ($this) {
            self::NUMBER, self::MONEY => [CrmSegmentOperator::GTE, CrmSegmentOperator::LTE, CrmSegmentOperator::EQ],
            self::DATE => [
                CrmSegmentOperator::WITHIN_DAYS,
                CrmSegmentOperator::NOT_WITHIN_DAYS,
                CrmSegmentOperator::BEFORE,
                CrmSegmentOperator::AFTER,
                CrmSegmentOperator::IS_EMPTY,
            ],
            self::ENTITY, self::OPTION => [CrmSegmentOperator::IN, CrmSegmentOperator::NOT_IN],
            self::FLAG => [CrmSegmentOperator::IS_TRUE, CrmSegmentOperator::IS_FALSE],
        };
    }
}
