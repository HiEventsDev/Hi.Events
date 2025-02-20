<?php

namespace HiEvents\DomainObjects\Enums;

enum QuestionBelongsTo
{
    use BaseEnum;

    case PRODUCT;
    case ORDER;
}
