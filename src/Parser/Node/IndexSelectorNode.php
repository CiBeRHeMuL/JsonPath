<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;

class IndexSelectorNode implements SelectorNodeInterface
{
    public function __construct(
        private int $index,
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "$this->index";
    }
}
