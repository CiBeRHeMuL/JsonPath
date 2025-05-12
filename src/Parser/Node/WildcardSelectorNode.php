<?php

namespace AndrewGos\JsonPath\Parser\Node;

class WildcardSelectorNode implements SelectorNodeInterface
{
    public function __toString(): string
    {
        return '*';
    }
}
