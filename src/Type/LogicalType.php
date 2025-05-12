<?php

namespace AndrewGos\JsonPath\Type;

readonly class LogicalType implements TypeInterface
{
    public function __construct(
        private bool $true,
    ) {
    }

    public function isTrue(): bool
    {
        return $this->true;
    }
}
