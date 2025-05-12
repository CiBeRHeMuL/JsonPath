<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\NodeInterface;

class LiteralNode implements NodeInterface
{
    public function __construct(
        private string|int|float|bool|null $value,
    ) {
    }

    public function getValue(): float|bool|int|string|null
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return match ($this->value) {
            null => 'null',
            true => 'true',
            false => 'false',
            default => "$this->value",
        };
    }
}
