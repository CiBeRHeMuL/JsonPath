<?php

namespace AndrewGos\JsonPath\Parser\Enum;

enum CompareOperatorEnum: string
{
    case Equal = '==';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
}

