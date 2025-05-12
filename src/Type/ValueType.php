<?php

namespace AndrewGos\JsonPath\Type;

use AndrewGos\JsonPath\Type\ValueTypeInterface;

readonly class ValueType implements ValueTypeInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
